<?php

namespace App\Http\Controllers\User;

use App\Exceptions\PeriodLockedException;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Bill;
use App\Models\Company;
use App\Models\FxRevaluation;
use App\Models\Invoice;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Period-end revaluation of open foreign-currency AR/AP balances — the
 * unrealized counterpart to the realized FX gain/loss that already posts
 * when a foreign-currency invoice/bill is paid at a different rate than it
 * was issued at. A company trading only in its own base currency never has
 * anything to revalue here; this only matters once a real foreign-currency
 * document exists.
 */
class FxRevaluationController extends Controller
{
    public function index()
    {
        $revaluations = FxRevaluation::with('journalEntry')->latest('as_of_date')->latest('id')->paginate(15);

        return view('user.fx-revaluations.index', compact('revaluations'));
    }

    public function create(Request $request)
    {
        $asOf = $request->filled('as_of') ? Carbon::parse($request->query('as_of'))->endOfDay() : now()->endOfDay();
        $company = Auth::user()->company;

        $groups = $this->openForeignBalances($company, $asOf);

        return view('user.fx-revaluations.create', ['asOf' => $asOf, 'groups' => $groups, 'company' => $company]);
    }

    public function store(Request $request, LedgerPostingService $ledger): RedirectResponse
    {
        $data = $request->validate([
            'as_of_date' => ['required', 'date'],
            'rates' => ['required', 'array', 'min:1'],
            'rates.*' => ['required', 'numeric', 'min:0.000001'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $company = Auth::user()->company;
        $asOf = Carbon::parse($data['as_of_date'])->endOfDay();

        // The rate table shown on the create form is never trusted from the
        // request beyond which currencies it names — the open documents and
        // their booked rates/balances are always recomputed fresh here, the
        // same figures the create form displayed.
        $groups = $this->openForeignBalances($company, $asOf);

        $lineRows = [];
        $arDelta = 0.0;
        $apDelta = 0.0;

        foreach ($groups as $currency => $group) {
            if (! isset($data['rates'][$currency])) {
                continue;
            }

            $revalRate = (float) $data['rates'][$currency];

            foreach (array_merge($group['invoices'], $group['bills']) as $doc) {
                $bookedBase = round($doc['balance'] * $doc['booked_rate'], 2);
                $revaluedBase = round($doc['balance'] * $revalRate, 2);
                $delta = round($revaluedBase - $bookedBase, 2);

                if (abs($delta) <= 0.004) {
                    continue;
                }

                $lineRows[] = [
                    'document_type' => $doc['type'],
                    'document_id' => $doc['id'],
                    'document_number' => $doc['number'],
                    'party_name' => $doc['party'],
                    'currency' => $currency,
                    'foreign_balance' => $doc['balance'],
                    'booked_rate' => $doc['booked_rate'],
                    'revaluation_rate' => $revalRate,
                    'booked_base_amount' => $bookedBase,
                    'revalued_base_amount' => $revaluedBase,
                    'unrealized_gain_loss' => $delta,
                ];

                if ($doc['type'] === 'invoice') {
                    $arDelta += $delta;
                } else {
                    $apDelta += $delta;
                }
            }
        }

        if (empty($lineRows)) {
            return back()->withInput()->withErrors(['rates' => __('Nothing to revalue — no open foreign-currency balance changed value at the rates given.')]);
        }

        try {
            $revaluation = DB::transaction(function () use ($company, $asOf, $data, $lineRows, $arDelta, $apDelta, $ledger) {
                $this->reversePreviousRun($company, $ledger);

                $revaluation = $company->fxRevaluations()->create([
                    'as_of_date' => $asOf,
                    'created_by' => Auth::id(),
                    'notes' => $data['notes'] ?? null,
                ]);

                foreach ($lineRows as $row) {
                    $revaluation->lines()->create($row);
                }

                $entry = $ledger->postFxRevaluation(
                    $company,
                    $revaluation->id,
                    __('FX revaluation as of :date', ['date' => $asOf->format('Y-m-d')]),
                    $asOf,
                    $arDelta,
                    $apDelta
                );

                $revaluation->update(['journal_entry_id' => $entry?->id]);

                return $revaluation;
            });
        } catch (PeriodLockedException $e) {
            return back()->withInput()->withErrors(['as_of_date' => $e->getMessage()]);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['rates' => $e->getMessage()]);
        }

        AuditLog::record('fx_revaluation.create', $revaluation, __('Posted FX revaluation as of :date', ['date' => $asOf->format('Y-m-d')]));

        return redirect()->route('app.fx-revaluations.show', $revaluation)->with('status', __('FX revaluation posted.'));
    }

    public function show(FxRevaluation $fxRevaluation)
    {
        $fxRevaluation->load('lines', 'journalEntry');

        return view('user.fx-revaluations.show', compact('fxRevaluation'));
    }

    /**
     * The most recent still-active revaluation (if any) is reversed before
     * a new one posts — every run always measures each document's own
     * booked exchange_rate against a fresh current rate, so only ever one
     * unrealized adjustment stands at a time instead of compounding on top
     * of an earlier estimate (see LedgerPostingService::postFxRevaluation).
     */
    private function reversePreviousRun(Company $company, LedgerPostingService $ledger): void
    {
        $previous = $company->fxRevaluations()->whereNull('reversed_at')->whereNotNull('journal_entry_id')->latest('as_of_date')->latest('id')->first();

        if (! $previous) {
            return;
        }

        $ledger->reverse($company, 'fx_revaluation', $previous->id, __('Reversal of FX revaluation as of :date', ['date' => $previous->as_of_date->format('Y-m-d')]));

        $previous->update(['reversed_at' => now()]);
    }

    /**
     * @return array<string, array{invoices: array<int, array>, bills: array<int, array>}>
     */
    private function openForeignBalances(Company $company, Carbon $asOf): array
    {
        $invoices = Invoice::with('client')
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->where('currency', '!=', $company->currency)
            ->where('issue_date', '<=', $asOf)
            ->get()
            ->filter(fn (Invoice $invoice) => $invoice->balanceDue() > 0.004);

        $bills = Bill::with('supplier')
            ->whereNotIn('status', ['draft', 'void'])
            ->where('currency', '!=', $company->currency)
            ->where('bill_date', '<=', $asOf)
            ->get()
            ->filter(fn (Bill $bill) => $bill->balanceDue() > 0.004);

        $groups = [];

        foreach ($invoices as $invoice) {
            $groups[$invoice->currency]['invoices'][] = [
                'type' => 'invoice',
                'id' => $invoice->id,
                'number' => $invoice->invoice_number,
                'party' => $invoice->client?->name ?? __('Unknown client'),
                'balance' => $invoice->balanceDue(),
                'booked_rate' => (float) $invoice->exchange_rate,
            ];
        }

        foreach ($bills as $bill) {
            $groups[$bill->currency]['bills'][] = [
                'type' => 'bill',
                'id' => $bill->id,
                'number' => $bill->bill_number,
                'party' => $bill->supplier?->name ?? __('Unknown supplier'),
                'balance' => $bill->balanceDue(),
                'booked_rate' => (float) $bill->exchange_rate,
            ];
        }

        foreach ($groups as $currency => &$group) {
            $group['invoices'] = $group['invoices'] ?? [];
            $group['bills'] = $group['bills'] ?? [];
        }
        unset($group);

        ksort($groups);

        return $groups;
    }
}
