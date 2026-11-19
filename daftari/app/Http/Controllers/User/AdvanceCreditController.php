<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\AdvanceCredit;
use App\Models\AdvanceCreditApplication;
use App\Models\AdvanceCreditPortion;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * "can we do this in our accounting system" — see this feature's own
 * migration (2026_10_21_010000_create_advance_credits_table.php) for the
 * full scenario this answers: crediting part of an advance (already
 * invoiced and paid, but not through the dedicated Prepayment Invoice
 * feature) against a new invoice, line by line, while keeping the rest
 * of that same advance in reserve for a later invoice.
 */
class AdvanceCreditController extends Controller
{
    public function index()
    {
        $credits = AdvanceCredit::with('client', 'sourceInvoice', 'portions.applications')->orderByDesc('id')->get();

        return view('user.advance-credits.index', ['credits' => $credits]);
    }

    public function create(Request $request)
    {
        $clients = Client::orderBy('name')->get();
        $selectedClientId = $request->integer('client_id') ?: null;
        $invoices = $selectedClientId
            ? Invoice::where('client_id', $selectedClientId)->orderByDesc('issue_date')->get()
            : collect();
        $projects = Project::orderBy('name')->get();

        return view('user.advance-credits.create', compact('clients', 'invoices', 'projects', 'selectedClientId'));
    }

    public function store(Request $request): RedirectResponse
    {
        $companyId = Auth::user()->company_id;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'client_id' => ['required', Rule::exists('clients', 'id')->where('company_id', $companyId)],
            'project_id' => ['nullable', Rule::exists('projects', 'id')->where('company_id', $companyId)],
            'source_invoice_id' => ['nullable', Rule::exists('invoices', 'id')->where('company_id', $companyId)->where('client_id', $request->integer('client_id'))],
            'notes' => ['nullable', 'string', 'max:2000'],
            'portions' => ['required', 'array', 'min:1'],
            'portions.*.description' => ['required', 'string', 'max:255'],
            'portions.*.amount' => ['required', 'numeric', 'min:0.01'],
            'portions.*.vat_rate' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $credit = DB::transaction(function () use ($data) {
            $credit = AdvanceCredit::create([
                'client_id' => $data['client_id'],
                'project_id' => $data['project_id'] ?? null,
                'source_invoice_id' => $data['source_invoice_id'] ?? null,
                'name' => $data['name'],
                'notes' => $data['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);

            foreach (array_values($data['portions']) as $sort => $row) {
                $vatAmount = round($row['amount'] * ($row['vat_rate'] / 100), 2);
                $credit->portions()->create([
                    'description' => $row['description'],
                    'amount' => $row['amount'],
                    'vat_rate' => $row['vat_rate'],
                    'vat_amount' => $vatAmount,
                    'total' => round($row['amount'] + $vatAmount, 2),
                    'sort_order' => $sort,
                ]);
            }

            return $credit;
        });

        return redirect()->route('app.advance-credits.show', $credit)->with('status', __('Advance credit created.'));
    }

    public function show(AdvanceCredit $advanceCredit)
    {
        $advanceCredit->load('client', 'sourceInvoice', 'portions.applications.invoice');

        return view('user.advance-credits.show', ['credit' => $advanceCredit]);
    }

    public function destroy(AdvanceCredit $advanceCredit): RedirectResponse
    {
        $advanceCredit->delete();

        return redirect()->route('app.advance-credits.index')->with('status', __('Advance credit deleted.'));
    }

    /**
     * Applies part (or all) of one portion's remaining balance against
     * an invoice — the operator's own "Less: subgrade advance—INV-00001"
     * line. Capped at both the portion's own remaining balance and the
     * invoice's own remaining balance due, so this can never apply more
     * credit than either side actually has room for.
     */
    public function apply(Request $request, Invoice $invoice): RedirectResponse
    {
        $companyId = Auth::user()->company_id;

        $data = $request->validate([
            'advance_credit_portion_id' => ['required', Rule::exists('advance_credit_portions', 'id')->where('company_id', $companyId)],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'invoice_item_id' => ['nullable', Rule::exists('invoice_items', 'id')->where('invoice_id', $invoice->id)],
        ]);

        $portion = AdvanceCreditPortion::with('advanceCredit')->findOrFail($data['advance_credit_portion_id']);

        if ($portion->advanceCredit->client_id !== $invoice->client_id) {
            return back()->withErrors(['advance_credit_portion_id' => __('This advance credit belongs to a different client.')]);
        }

        $remainingPortion = $portion->remainingTotal();
        $remainingInvoice = max($invoice->balanceDue(), 0);
        $applyTotal = round(min((float) $data['amount'], $remainingPortion, $remainingInvoice), 2);

        if ($applyTotal <= 0) {
            return back()->withErrors(['amount' => __('Nothing available to apply — the portion or the invoice has no remaining balance.')]);
        }

        // Same VAT-exclusive/VAT split as the portion's own original rate.
        $vatRateFraction = (float) $portion->vat_rate / 100;
        $amountExclVat = round($applyTotal / (1 + $vatRateFraction), 2);
        $vatAmount = round($applyTotal - $amountExclVat, 2);

        AdvanceCreditApplication::create([
            'advance_credit_portion_id' => $portion->id,
            'invoice_id' => $invoice->id,
            'invoice_item_id' => $data['invoice_item_id'] ?? null,
            'amount' => $amountExclVat,
            'vat_amount' => $vatAmount,
            'total' => $applyTotal,
            'created_by' => Auth::id(),
        ]);

        return back()->with('status', __('Advance credit applied.'));
    }

    public function unapply(Invoice $invoice, AdvanceCreditApplication $application): RedirectResponse
    {
        abort_unless($application->invoice_id === $invoice->id, 404);

        $application->delete();

        return back()->with('status', __('Advance credit application removed.'));
    }
}
