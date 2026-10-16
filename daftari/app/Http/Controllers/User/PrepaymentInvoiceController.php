<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Controllers\User\Concerns\ResolvesPerPage;
use App\Jobs\SyncPrepaymentInvoiceToZatca;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\PrepaymentInvoice;
use App\Models\Project;
use App\Models\TaxRate;
use App\Services\MpdfRenderer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * ZATCA compliance gap: an advance/deposit collected before the main
 * supply must be issued as its own formal "prepayment tax invoice"
 * (InvoiceTypeCode 386) — Daftari had no way to issue one at all (see
 * the create_prepayment_invoices_table migration). This is deliberately
 * NOT itemized the way Invoice/CreditNote/DebitNote are — a real
 * prepayment invoice is a single advance amount against a client or
 * project, priced goods/services aren't known yet.
 */
class PrepaymentInvoiceController extends Controller
{
    use ResolvesPerPage;

    public function index(Request $request)
    {
        $prepaymentInvoices = PrepaymentInvoice::with('client', 'project', 'appliedToInvoice')
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->paginate($this->resolvePerPage($request))
            ->withQueryString();

        return view('user.prepayment-invoices.index', compact('prepaymentInvoices'));
    }

    public function create()
    {
        $company = Auth::user()->company;

        return view('user.prepayment-invoices.form', [
            'clients' => Client::orderBy('name')->get(),
            'projects' => Project::orderBy('name')->get(),
            'taxRates' => TaxRate::where('is_active', true)->orderBy('name')->get(),
            'nextNumberPreview' => $company->prepayment_prefix.'-'.str_pad((string) $company->next_prepayment_number, 5, '0', STR_PAD_LEFT),
        ]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'client_id' => ['required', Rule::exists('clients', 'id')->where('company_id', Auth::user()->company_id)],
            'project_id' => ['nullable', Rule::exists('projects', 'id')->where('company_id', Auth::user()->company_id)],
            'tax_rate_id' => ['required', Rule::exists('tax_rates', 'id')->where('company_id', Auth::user()->company_id)],
            'type' => ['required', 'in:standard,simplified'],
            'issue_date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string'],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $prepayment = DB::transaction(function () use ($data) {
            $company = Auth::user()->company;
            $taxRate = TaxRate::findOrFail($data['tax_rate_id']);

            $amount = round((float) $data['amount'], 2);
            $vatAmount = round($amount * ((float) $taxRate->rate / 100), 2);

            return PrepaymentInvoice::create([
                'client_id' => $data['client_id'],
                'project_id' => $data['project_id'] ?? null,
                'tax_rate_id' => $taxRate->id,
                'created_by' => Auth::id(),
                'prepayment_number' => $company->nextPrepaymentNumber(),
                'type' => $data['type'],
                'status' => 'issued',
                'issue_date' => $data['issue_date'],
                'description' => $data['description'] ?? null,
                'amount' => $amount,
                'vat_rate' => $taxRate->rate,
                'vat_amount' => $vatAmount,
                'total' => $amount + $vatAmount,
                'currency' => $company->currency ?: 'SAR',
                'notes' => $data['notes'] ?? null,
            ]);
        });

        AuditLog::record('prepayment_invoice.create', $prepayment, __('Issued prepayment invoice :number', ['number' => $prepayment->prepayment_number]));

        if ($prepayment->company->zatca_sync_frequency === 'instant') {
            SyncPrepaymentInvoiceToZatca::dispatch($prepayment->id);
        }

        return redirect()->route('app.prepayment-invoices.show', $prepayment)->with('status', __('Prepayment invoice :number issued.', ['number' => $prepayment->prepayment_number]));
    }

    public function show(PrepaymentInvoice $prepaymentInvoice)
    {
        $prepaymentInvoice->load('client', 'project', 'taxRate', 'appliedToInvoice');

        $zatcaLog = $prepaymentInvoice->zatcaInvoiceLogs()->latest('id')->first();

        return view('user.prepayment-invoices.show', compact('prepaymentInvoice', 'zatcaLog'));
    }

    public function destroy(PrepaymentInvoice $prepaymentInvoice)
    {
        if ($prepaymentInvoice->isZatcaSynced()) {
            return back()->withErrors(['prepayment' => __('This prepayment invoice has already been cleared/reported to ZATCA and is now part of an immutable tax record — it cannot be deleted.')]);
        }

        if ($prepaymentInvoice->isApplied()) {
            return back()->withErrors(['prepayment' => __('This prepayment invoice has already been applied to an invoice and cannot be deleted.')]);
        }

        // Compliance gap found while auditing ZATCA record-keeping: once
        // issued (the only status store() ever creates today — a VAT-
        // relevant advance was received the moment this exists), it's a
        // real financial record regardless of ZATCA sync status.
        if ($prepaymentInvoice->status !== 'draft' && \App\Support\RecordRetentionPolicy::isWithinRetentionWindow($prepaymentInvoice->issue_date)) {
            return back()->withErrors(['prepayment' => __('This prepayment invoice is within the :years-year record retention period required by Saudi VAT law and cannot be deleted.', ['years' => \App\Support\RecordRetentionPolicy::RETENTION_YEARS])]);
        }

        AuditLog::record('prepayment_invoice.delete', $prepaymentInvoice, __('Deleted prepayment invoice :number', ['number' => $prepaymentInvoice->prepayment_number]));

        $prepaymentInvoice->delete();

        return redirect()->route('app.prepayment-invoices.index')->with('status', __('Prepayment invoice deleted.'));
    }

    public function downloadPdf(PrepaymentInvoice $prepaymentInvoice, MpdfRenderer $renderer)
    {
        $prepaymentInvoice->load('client', 'company');

        $pseudoLine = (object) [
            'description' => $prepaymentInvoice->description ?: __('Advance payment'),
            'name_ar' => null,
            'quantity' => 1,
            'unit_price' => (float) $prepaymentInvoice->amount,
            'line_total' => (float) $prepaymentInvoice->amount,
            'vat_rate' => (float) $prepaymentInvoice->vat_rate,
            'vat_amount' => (float) $prepaymentInvoice->vat_amount,
            'unit' => null,
            'item' => null,
        ];

        $doc = [
            'type_label' => __('Prepayment Invoice'),
            'type_label_ar' => 'فاتورة ضريبية مقدمة',
            'number' => $prepaymentInvoice->prepayment_number,
            'date_label' => __('Issued'),
            'date' => $prepaymentInvoice->issue_date,
            'issue_time' => $prepaymentInvoice->created_at,
            'party_label' => __('Billed to'),
            'party_label_ar' => 'العميل',
            'party' => $prepaymentInvoice->client,
            'qr_code' => $prepaymentInvoice->qr_code,
            'zatca_status' => $prepaymentInvoice->zatcaInvoiceLogs()->whereIn('status', ['cleared', 'reported'])->latest('id')->value('status'),
            'lines' => [$pseudoLine],
            'subtotal' => $prepaymentInvoice->amount,
            'discount_total' => 0,
            'vat_total' => $prepaymentInvoice->vat_amount,
            'total' => $prepaymentInvoice->total,
            'notes' => $prepaymentInvoice->notes,
        ];

        $pdf = $renderer->render('documents.print.pdf', [
            'doc' => $doc,
            'company' => $prepaymentInvoice->company,
            'template' => $prepaymentInvoice->company->defaultTemplateFor('invoice'),
        ]);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$prepaymentInvoice->prepayment_number.'.pdf"',
        ]);
    }

    public function downloadXml(PrepaymentInvoice $prepaymentInvoice)
    {
        $log = $prepaymentInvoice->zatcaInvoiceLogs()->whereIn('status', ['cleared', 'reported'])->latest('id')->first();

        if (! $log || ! $log->xml_payload) {
            return back()->withErrors(['prepayment' => __('This prepayment invoice has not been cleared or reported to ZATCA yet — there is no signed XML to download.')]);
        }

        return response($log->xml_payload, 200, [
            'Content-Type' => 'application/xml',
            'Content-Disposition' => 'attachment; filename="'.$prepaymentInvoice->prepayment_number.'.xml"',
        ]);
    }
}
