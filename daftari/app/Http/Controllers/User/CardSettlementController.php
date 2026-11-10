<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Controllers\User\Concerns\ExportsCsv;
use App\Http\Controllers\User\Concerns\ResolvesPerPage;
use App\Models\AuditLog;
use App\Models\BankAccount;
use App\Models\CardSettlement;
use App\Models\PaymentMode;
use App\Services\Accounting\LedgerPostingService;
use App\Services\MpdfRenderer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * "add more... Card/POS Settlement workflow" — a card/POS sale posts
 * its gross amount into a clearing account the moment it's rung up
 * (see LedgerPostingService::postPosSale()). This records the real
 * bank settlement that follows: the processor's fee (MDR) + VAT on
 * that fee, netted down to what actually landed in the bank, clearing
 * that balance out — see postCardSettlement()'s own doc block.
 */
class CardSettlementController extends Controller
{
    use ExportsCsv, ResolvesPerPage;

    public function index(Request $request)
    {
        $company = Auth::user()->company;
        PaymentMode::seedDefaults($company->id);

        $settlements = CardSettlement::with('paymentMode', 'bankAccount')
            ->latest('settlement_date')->latest('id')
            ->paginate($this->resolvePerPage($request))->withQueryString();

        if ($request->query('export') === 'csv') {
            return $this->csvResponse('card-settlements.csv',
                [__('Date'), __('Payment mode'), __('Bank account'), __('Gross'), __('Fee'), __('Fee VAT'), __('Net deposited'), __('Reference'), __('Status')],
                CardSettlement::with('paymentMode', 'bankAccount')->orderByDesc('settlement_date')->get()->map(fn (CardSettlement $s) => [
                    $s->settlement_date->toDateString(), $s->paymentMode?->name, $s->bankAccount->name,
                    number_format((float) $s->gross_amount, 2, '.', ''), number_format((float) $s->fee_amount, 2, '.', ''),
                    number_format((float) $s->fee_vat_amount, 2, '.', ''), number_format((float) $s->net_amount, 2, '.', ''),
                    $s->reference, $s->status,
                ]));
        }

        return view('user.card-settlements.index', compact('settlements'));
    }

    public function create()
    {
        $company = Auth::user()->company;
        PaymentMode::seedDefaults($company->id);

        return view('user.card-settlements.form', [
            'paymentModes' => PaymentMode::where('company_id', $company->id)->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'bankAccounts' => BankAccount::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, LedgerPostingService $ledger)
    {
        $data = $this->validated($request);

        $settlement = DB::transaction(function () use ($data, $ledger) {
            $settlement = CardSettlement::create($data + ['created_by' => Auth::id(), 'status' => 'posted']);
            $ledger->postCardSettlement($settlement);

            return $settlement;
        });

        AuditLog::record('card_settlement.create', $settlement, __('Recorded card settlement of :amount net', ['amount' => $settlement->net_amount]));

        return redirect()->route('app.card-settlements.show', $settlement)->with('status', __('Settlement recorded.'));
    }

    public function show(CardSettlement $cardSettlement)
    {
        $cardSettlement->load('paymentMode', 'bankAccount', 'creator');

        return view('user.card-settlements.show', ['settlement' => $cardSettlement]);
    }

    public function downloadPdf(CardSettlement $cardSettlement, MpdfRenderer $renderer)
    {
        $cardSettlement->load('paymentMode', 'bankAccount', 'creator');
        $company = $cardSettlement->company;

        $pdf = $renderer->render('documents.print.card-settlement-pdf', [
            'settlement' => $cardSettlement,
            'company' => $company,
            'template' => $company->defaultTemplateFor('card_settlement'),
        ]);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="card-settlement-'.$cardSettlement->id.'.pdf"',
        ]);
    }

    public function void(CardSettlement $cardSettlement, LedgerPostingService $ledger)
    {
        if ($cardSettlement->status === 'void') {
            return back();
        }

        DB::transaction(function () use ($cardSettlement, $ledger) {
            $cardSettlement->update(['status' => 'void']);
            $ledger->reverse($cardSettlement->company, 'card_settlement', $cardSettlement->id, __('Card settlement voided'));
        });

        AuditLog::record('card_settlement.void', $cardSettlement, __('Voided card settlement of :amount net', ['amount' => $cardSettlement->net_amount]));

        return back()->with('status', __('Settlement voided.'));
    }

    public function destroy(CardSettlement $cardSettlement)
    {
        if ($cardSettlement->status !== 'void') {
            return back()->with('status', __('Void this settlement first so its ledger entry is safely reversed, then delete it.'));
        }

        AuditLog::record('card_settlement.delete', $cardSettlement, __('Deleted voided card settlement of :amount net', ['amount' => $cardSettlement->net_amount]));

        $cardSettlement->delete();

        return redirect()->route('app.card-settlements.index')->with('status', __('Settlement deleted.'));
    }

    private function validated(Request $request): array
    {
        $companyId = Auth::user()->company_id;

        $data = $request->validate([
            'payment_mode_id' => ['nullable', Rule::exists('payment_modes', 'id')->where('company_id', $companyId)],
            'bank_account_id' => ['required', Rule::exists('bank_accounts', 'id')->where('company_id', $companyId)],
            'settlement_date' => ['required', 'date'],
            'gross_amount' => ['required', 'numeric', 'min:0.01'],
            'fee_amount' => ['nullable', 'numeric', 'min:0'],
            'fee_vat_amount' => ['nullable', 'numeric', 'min:0'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $data['fee_amount'] = (float) ($data['fee_amount'] ?? 0);
        $data['fee_vat_amount'] = (float) ($data['fee_vat_amount'] ?? 0);
        $data['net_amount'] = round((float) $data['gross_amount'] - $data['fee_amount'] - $data['fee_vat_amount'], 2);

        if ($data['net_amount'] < 0) {
            throw ValidationException::withMessages(['fee_amount' => __('The fee and its VAT cannot add up to more than the gross amount.')]);
        }

        return $data;
    }
}
