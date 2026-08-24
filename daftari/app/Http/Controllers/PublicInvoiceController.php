<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\PaymentGateway;
use App\Services\MpdfRenderer;
use App\Services\Payments\PaymentCheckoutService;

class PublicInvoiceController extends Controller
{
    /**
     * A shareable, no-login-required link a company can send a client
     * directly ("View & pay this invoice") instead of only a PDF
     * attachment. Looked up by id + a random public_token — not the
     * regular {invoice} route-model-binding, since that goes through
     * BelongsToCompany's global scope, which needs an authenticated
     * user's company_id and would always 404 here.
     */
    public function show(int $id, string $token)
    {
        $invoice = Invoice::withoutGlobalScopes()->with('items', 'client', 'bankAccount', 'company')->findOrFail($id);

        abort_unless(hash_equals($invoice->public_token, $token), 404);
        abort_if($invoice->status === 'draft', 404);

        $doc = $this->buildDoc($invoice);
        $template = $invoice->company->defaultTemplateFor('invoice');

        $enabledProviders = PaymentGateway::where('company_id', $invoice->company_id)
            ->where('is_enabled', true)
            ->whereIn('provider', PaymentGateway::whereNull('company_id')->where('is_enabled', true)->pluck('provider'))
            ->pluck('provider')
            ->all();

        return view('public.invoice-show', [
            'invoice' => $invoice,
            'company' => $invoice->company,
            'doc' => $doc,
            'template' => $template,
            'enabledProviders' => $enabledProviders,
        ]);
    }

    public function pay(int $id, string $token, string $provider, PaymentCheckoutService $checkout)
    {
        $invoice = Invoice::withoutGlobalScopes()->with('client', 'company')->findOrFail($id);

        abort_unless(hash_equals($invoice->public_token, $token), 404);
        abort_if($invoice->status === 'draft', 404);
        abort_if($invoice->balanceDue() <= 0.01, 404);

        $gateway = PaymentGateway::where('company_id', $invoice->company_id)
            ->where('provider', $provider)
            ->where('is_enabled', true)
            ->first();

        $platformAllowsProvider = PaymentGateway::whereNull('company_id')->where('provider', $provider)->where('is_enabled', true)->exists();

        abort_unless($gateway && $platformAllowsProvider, 404);

        $transaction = $checkout->start(
            $gateway,
            $invoice,
            $invoice->balanceDue(),
            $invoice->currency,
            __('Invoice :number', ['number' => $invoice->invoice_number]),
            [
                'name' => $invoice->client?->name ?? '',
                'email' => $invoice->client?->email ?? '',
                'phone' => $invoice->client?->phone ?? '',
            ],
            route('public.invoices.show', ['id' => $invoice->id, 'token' => $invoice->public_token])
        );

        return redirect()->away($transaction->checkout_url);
    }

    public function downloadPdf(int $id, string $token, MpdfRenderer $renderer)
    {
        $invoice = Invoice::withoutGlobalScopes()->with('items', 'client', 'bankAccount', 'company')->findOrFail($id);

        abort_unless(hash_equals($invoice->public_token, $token), 404);
        abort_if($invoice->status === 'draft', 404);

        $pdf = $renderer->render('documents.print.pdf', [
            'doc' => $this->buildDoc($invoice),
            'company' => $invoice->company,
            'template' => $invoice->company->defaultTemplateFor('invoice'),
        ]);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$invoice->invoice_number.'.pdf"',
        ]);
    }

    private function buildDoc(Invoice $invoice): array
    {
        $bankAccount = $invoice->bankAccount ?? $invoice->company->defaultBankAccount();

        return [
            'type_label' => __('Tax Invoice'),
            'type_label_ar' => 'فاتورة ضريبية',
            'number' => $invoice->invoice_number,
            'date_label' => __('Issued'),
            'date' => $invoice->issue_date,
            'date2_label' => __('Due'),
            'date2_label_ar' => 'الاستحقاق',
            'date2' => $invoice->due_date,
            'party_label' => __('Bill to'),
            'party_label_ar' => 'العميل',
            'party' => $invoice->client,
            'qr_code' => $invoice->qr_code,
            'zatca_status' => $invoice->zatcaInvoiceLogs()->whereIn('status', ['cleared', 'reported'])->latest('id')->value('status'),
            'lines' => $invoice->items,
            'subtotal' => $invoice->subtotal,
            'discount_total' => $invoice->discount_total,
            'vat_total' => $invoice->vat_total,
            'total' => $invoice->total,
            'extra_rows' => array_values(array_filter([
                $invoice->retention_amount > 0 ? [
                    'label' => __('Retention held').' ('.rtrim(rtrim(number_format($invoice->retention_rate, 2), '0'), '.').'%)',
                    'value' => $invoice->retention_amount,
                ] : null,
                ['label' => __('Paid'), 'value' => $invoice->amount_paid],
                [
                    'label' => __('Balance due'), 'value' => $invoice->balanceDue(), 'emphasis' => true,
                    'variant' => $invoice->balanceDue() > 0 ? 'red' : null,
                ],
            ])),
            'bank_account' => $bankAccount,
            'salesperson' => $invoice->salesperson,
            'notes' => $invoice->notes,
        ];
    }
}
