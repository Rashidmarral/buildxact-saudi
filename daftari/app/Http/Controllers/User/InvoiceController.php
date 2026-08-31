<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Controllers\User\Concerns\ExportsCsv;
use App\Jobs\SyncInvoiceToZatca;
use App\Mail\InvoiceMail;
use App\Models\Attachment;
use App\Models\AuditLog;
use App\Models\BankAccount;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\Project;
use App\Models\Salesperson;
use App\Models\SmsConfig;
use App\Models\TaxRate;
use App\Models\Warehouse;
use App\Models\Webhook;
use App\Models\WhatsappConfig;
use App\Services\Accounting\LedgerPostingService;
use App\Services\MpdfRenderer;
use App\Services\SmsService;
use App\Services\WhatsAppService;
use App\Support\Currencies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class InvoiceController extends Controller
{
    use ExportsCsv;

    public function index(Request $request)
    {
        $query = Invoice::with('client')
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->orderByDesc('issue_date')
            ->orderByDesc('id');

        if ($request->query('export') === 'csv') {
            return $this->csvResponse(
                'invoices.csv',
                [__('Invoice'), __('Client'), __('Date'), __('Total'), __('Balance due'), __('Status')],
                $query->get()->map(fn ($invoice) => [
                    $invoice->invoice_number,
                    $invoice->client->name,
                    $invoice->issue_date->format('Y-m-d'),
                    number_format($invoice->total, 2),
                    number_format($invoice->balanceDue(), 2),
                    $invoice->status,
                ])
            );
        }

        $invoices = $query->paginate(20)->withQueryString();

        return view('user.invoices.index', compact('invoices'));
    }

    public function create()
    {
        $company = Auth::user()->company;

        return view('user.invoices.form', [
            'invoice' => new Invoice(['issue_date' => now()->toDateString(), 'due_date' => now()->addDays(30)->toDateString(), 'currency' => $company->currency, 'exchange_rate' => 1]),
            'clients' => Client::orderBy('name')->get(),
            'items' => Item::where('is_active', true)->with('baseUnit', 'itemUnits.unit')->orderBy('name')->get(),
            'salespersons' => Salesperson::where('is_active', true)->orderBy('name')->get(),
            'projects' => Project::orderBy('name')->get(),
            'bankAccounts' => BankAccount::where('is_active', true)->orderBy('name')->get(),
            'warehouses' => Warehouse::orderBy('name')->get(),
            'currencies' => Currencies::catalog(),
            'nextNumberPreview' => $company->invoice_prefix.'-'.str_pad((string) $company->next_invoice_number, 5, '0', STR_PAD_LEFT),
        ]);
    }

    public function store(Request $request, LedgerPostingService $ledger)
    {
        if (Auth::user()->company->hasReachedPlanLimit('invoices')) {
            return back()->withErrors(['plan_limit' => __('You have reached your plan\'s monthly invoice limit. Upgrade your plan to create more invoices this billing period.')])->withInput();
        }

        $data = $this->validated($request);
        $sendImmediately = $request->boolean('send_immediately');

        $invoice = DB::transaction(function () use ($data) {
            $company = Auth::user()->company;
            $subtotal = collect($data['items'])->sum(fn ($row) => $row['quantity'] * $row['unit_price']);
            $retentionAmount = round($subtotal * (($data['retention_rate'] ?? 0) / 100), 2);

            $invoice = Invoice::create([
                'client_id' => $data['client_id'],
                'bank_account_id' => $data['bank_account_id'] ?? null,
                'warehouse_id' => $data['warehouse_id'] ?? null,
                'branch_id' => $company->default_branch_id,
                'salesperson_id' => $data['salesperson_id'] ?? null,
                'project_id' => $data['project_id'] ?? null,
                'created_by' => Auth::id(),
                'invoice_number' => $company->nextInvoiceNumber(),
                'type' => $data['type'],
                'status' => 'draft',
                'issue_date' => $data['issue_date'],
                'due_date' => $data['due_date'] ?? null,
                'discount_total' => $data['discount_total'] ?? 0,
                'retention_rate' => $data['retention_rate'] ?? 0,
                'retention_amount' => $retentionAmount,
                'currency' => $data['currency'] ?? $company->currency,
                'exchange_rate' => ($data['currency'] ?? $company->currency) === $company->currency ? 1 : ($data['exchange_rate'] ?? 1),
                'notes' => $data['notes'] ?? null,
            ]);

            $this->syncItems($invoice, $data['items']);
            $invoice->recalculateTotals();

            return $invoice;
        });

        AuditLog::record('invoice.create', $invoice, __('Created invoice :number', ['number' => $invoice->invoice_number]));

        Webhook::trigger($invoice->company_id, 'invoice.created', $this->webhookPayload($invoice));

        if ($sendImmediately) {
            $this->doSend($invoice, $ledger);
        }

        return redirect()->route('app.invoices.show', $invoice)->with('status', $sendImmediately ? __('Invoice created and sent.') : __('Invoice created.'));
    }

    public function show(Invoice $invoice)
    {
        $invoice->load('items', 'client', 'invoicePayments', 'bankAccount', 'warehouse', 'attachments');
        $template = $invoice->company->defaultTemplateFor('invoice');
        $whatsappEnabled = (bool) WhatsappConfig::where('is_enabled', true)->exists();
        $smsEnabled = (bool) SmsConfig::where('is_enabled', true)->exists();

        return view('user.invoices.show', compact('invoice', 'template', 'whatsappEnabled', 'smsEnabled'));
    }

    public function downloadPdf(Invoice $invoice, MpdfRenderer $renderer)
    {
        $pdf = $renderer->render('documents.print.pdf', $this->pdfData($invoice));

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$invoice->invoice_number.'.pdf"',
        ]);
    }

    public function emailInvoice(Invoice $invoice, Request $request, MpdfRenderer $renderer)
    {
        $recipient = $invoice->client->email;

        if (! $recipient) {
            return back()->withErrors(['invoice' => __('This client has no email address on file. Add one on the client record first.')]);
        }

        $pdfBinary = $renderer->render('documents.print.pdf', $this->pdfData($invoice));

        Mail::to($recipient)->send(new InvoiceMail($invoice, $pdfBinary));

        return back()->with('status', __('Invoice emailed to :email.', ['email' => $recipient]));
    }

    /**
     * Sends the client a WhatsApp notification with the invoice number,
     * total, and its public pay link — the same public_token link used by
     * emailInvoice's "view online" URL. This is a business-initiated
     * message, so it can only go out as a template message using whatever
     * template the company has already had approved in their own Meta
     * Business account (see WhatsappSettingsController) — Daftari expects
     * that template's body to take 4 positional parameters in this order:
     * client name, invoice number, total (with currency), pay link.
     */
    public function sendWhatsapp(Invoice $invoice, WhatsAppService $whatsapp)
    {
        $config = WhatsappConfig::first();
        abort_unless($config && $config->is_enabled, 404);

        $phone = $invoice->client->mobile ?: $invoice->client->phone;

        if (! $phone) {
            return back()->withErrors(['invoice' => __('This client has no phone number on file. Add one on the client record first.')]);
        }

        $payUrl = route('public.invoices.show', [$invoice->id, $invoice->public_token]);

        $result = $whatsapp->sendTemplateMessage($config, $phone, [
            $invoice->client->name,
            $invoice->invoice_number,
            number_format($invoice->total, 2).' '.$invoice->currency,
            $payUrl,
        ]);

        if (! $result['success']) {
            return back()->withErrors(['invoice' => __('WhatsApp send failed: :error', ['error' => $result['error']])]);
        }

        AuditLog::record('invoice.whatsapp_sent', $invoice, __('Sent invoice #:number via WhatsApp', ['number' => $invoice->invoice_number]));

        return back()->with('status', __('Invoice sent via WhatsApp.'));
    }

    /**
     * Sends the client a plain-text SMS with the invoice number, total, and
     * its public pay link. Unlike WhatsApp, SMS has no template-approval
     * requirement, so the message is composed here directly.
     */
    public function sendSms(Invoice $invoice, SmsService $sms)
    {
        $config = SmsConfig::first();
        abort_unless($config && $config->is_enabled, 404);

        $phone = $invoice->client->mobile ?: $invoice->client->phone;

        if (! $phone) {
            return back()->withErrors(['invoice' => __('This client has no phone number on file. Add one on the client record first.')]);
        }

        $payUrl = route('public.invoices.show', [$invoice->id, $invoice->public_token]);

        $message = __(':company: Invoice :number for :total is ready. Pay online: :link', [
            'company' => $invoice->company->name,
            'number' => $invoice->invoice_number,
            'total' => number_format($invoice->total, 2).' '.$invoice->currency,
            'link' => $payUrl,
        ]);

        $result = $sms->send($config, $phone, $message);

        if (! $result['success']) {
            return back()->withErrors(['invoice' => __('SMS send failed: :error', ['error' => $result['error']])]);
        }

        AuditLog::record('invoice.sms_sent', $invoice, __('Sent invoice #:number via SMS', ['number' => $invoice->invoice_number]));

        return back()->with('status', __('Invoice sent via SMS.'));
    }

    private function pdfData(Invoice $invoice): array
    {
        $invoice->loadMissing('items', 'client', 'bankAccount');

        $bankAccount = $invoice->bankAccount ?? $invoice->company->defaultBankAccount();

        $doc = [
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
            'currency' => $invoice->currency,
            'subtotal' => $invoice->subtotal,
            'discount_total' => $invoice->discount_total,
            'vat_total' => $invoice->vat_total,
            'total' => $invoice->total,
            'extra_rows' => array_values(array_filter([
                $invoice->currency !== $invoice->company->currency ? [
                    'label' => __(':currency equivalent (rate :rate)', ['currency' => $invoice->company->currency, 'rate' => rtrim(rtrim(number_format($invoice->exchange_rate, 6), '0'), '.')]),
                    'value' => round($invoice->total * $invoice->exchange_rate, 2),
                    'currency' => $invoice->company->currency,
                ] : null,
                $invoice->retention_amount > 0 ? [
                    'label' => __('Retention held').' ('.rtrim(rtrim(number_format($invoice->retention_rate, 2), '0'), '.').'%)',
                    'value' => $invoice->retention_amount,
                ] : null,
                ['label' => __('Paid'), 'value' => $invoice->amount_paid],
                ['label' => __('Balance due'), 'value' => $invoice->balanceDue()],
            ])),
            'bank_account' => $bankAccount,
            'salesperson' => $invoice->salesperson,
            'notes' => $invoice->notes,
        ];

        return [
            'doc' => $doc,
            'company' => $invoice->company,
            'template' => $invoice->company->defaultTemplateFor('invoice'),
        ];
    }

    public function downloadXml(Invoice $invoice)
    {
        $log = $invoice->zatcaInvoiceLogs()->whereIn('status', ['cleared', 'reported'])->latest('id')->first();

        if (! $log || ! $log->xml_payload) {
            return back()->withErrors(['invoice' => __('This invoice has not been cleared or reported to ZATCA yet — there is no signed XML to download.')]);
        }

        return response($log->xml_payload, 200, [
            'Content-Type' => 'application/xml',
            'Content-Disposition' => 'attachment; filename="'.$invoice->invoice_number.'.xml"',
        ]);
    }

    public function edit(Invoice $invoice)
    {
        if ($invoice->isZatcaLocked()) {
            return redirect()->route('app.invoices.show', $invoice)
                ->withErrors(['invoice' => __('This invoice has been cleared/reported to ZATCA and is now part of an immutable tax record — it can no longer be edited. Issue a credit note to correct it instead.')]);
        }

        $invoice->load('items');

        return view('user.invoices.form', [
            'invoice' => $invoice,
            'clients' => Client::orderBy('name')->get(),
            'items' => Item::where('is_active', true)->with('baseUnit', 'itemUnits.unit')->orderBy('name')->get(),
            'salespersons' => Salesperson::where('is_active', true)->orderBy('name')->get(),
            'projects' => Project::orderBy('name')->get(),
            'bankAccounts' => BankAccount::where('is_active', true)->orderBy('name')->get(),
            'warehouses' => Warehouse::orderBy('name')->get(),
            'currencies' => Currencies::catalog(),
        ]);
    }

    public function update(Request $request, Invoice $invoice)
    {
        if ($invoice->isZatcaLocked()) {
            return redirect()->route('app.invoices.show', $invoice)
                ->withErrors(['invoice' => __('This invoice has been cleared/reported to ZATCA and is now part of an immutable tax record — it can no longer be edited. Issue a credit note to correct it instead.')]);
        }

        $data = $this->validated($request);

        DB::transaction(function () use ($invoice, $data) {
            $company = $invoice->company;
            $subtotal = collect($data['items'])->sum(fn ($row) => $row['quantity'] * $row['unit_price']);
            $retentionAmount = round($subtotal * (($data['retention_rate'] ?? 0) / 100), 2);
            $currency = $data['currency'] ?? $company->currency;

            $invoice->update([
                'client_id' => $data['client_id'],
                'bank_account_id' => $data['bank_account_id'] ?? null,
                'warehouse_id' => $data['warehouse_id'] ?? null,
                'salesperson_id' => $data['salesperson_id'] ?? null,
                'project_id' => $data['project_id'] ?? null,
                'type' => $data['type'],
                'issue_date' => $data['issue_date'],
                'due_date' => $data['due_date'] ?? null,
                'discount_total' => $data['discount_total'] ?? 0,
                'retention_rate' => $data['retention_rate'] ?? 0,
                'retention_amount' => $retentionAmount,
                'currency' => $currency,
                'exchange_rate' => $currency === $company->currency ? 1 : ($data['exchange_rate'] ?? 1),
                'notes' => $data['notes'] ?? null,
            ]);

            $invoice->items()->delete();
            $this->syncItems($invoice, $data['items']);
            $invoice->recalculateTotals();
        });

        return redirect()->route('app.invoices.show', $invoice)->with('status', __('Invoice updated.'));
    }

    public function destroy(Invoice $invoice)
    {
        if ($invoice->isZatcaLocked()) {
            return back()->withErrors(['invoice' => __('This invoice has been cleared/reported to ZATCA and cannot be deleted. Issue a credit note to correct it instead.')]);
        }

        if ($invoice->status !== 'draft') {
            return back()->withErrors(['invoice' => __('Only draft invoices can be deleted. Cancel this invoice instead to keep an audit trail.')]);
        }

        $invoice->delete();

        return redirect()->route('app.invoices.index')->with('status', __('Invoice deleted.'));
    }

    public function send(Invoice $invoice, LedgerPostingService $ledger)
    {
        $this->doSend($invoice, $ledger);

        return back()->with('status', __('Invoice marked as sent.'));
    }

    public function cancel(Invoice $invoice, LedgerPostingService $ledger)
    {
        if (in_array($invoice->status, ['draft', 'cancelled'], true)) {
            return back()->withErrors(['invoice' => __('This invoice cannot be cancelled.')]);
        }

        if ($invoice->isZatcaLocked()) {
            return back()->withErrors(['invoice' => __('This invoice has been cleared/reported to ZATCA and cannot be cancelled directly — issue a credit note instead, which correctly notifies ZATCA of the adjustment.')]);
        }

        DB::transaction(function () use ($invoice, $ledger) {
            if ($invoice->stock_deducted) {
                $this->applyStock($invoice, 1);
                $invoice->stock_deducted = false;
            }

            $invoice->status = 'cancelled';
            $invoice->save();

            $ledger->reverse($invoice->company, 'invoice', $invoice->id, __('Invoice :number cancelled', ['number' => $invoice->invoice_number]));
        });

        AuditLog::record('invoice.cancel', $invoice, __('Cancelled invoice :number', ['number' => $invoice->invoice_number]));

        return back()->with('status', __('Invoice cancelled.'));
    }

    public function storePayment(Request $request, Invoice $invoice, LedgerPostingService $ledger)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:'.max($invoice->balanceDue(), 0.01)],
            'paid_at' => ['required', 'date'],
            'method' => ['nullable', 'string', 'max:30'],
            'reference' => ['nullable', 'string', 'max:255'],
        ]);

        $wasPaid = $invoice->status === 'paid';

        DB::transaction(function () use ($invoice, $data, $ledger) {
            $payment = $invoice->invoicePayments()->create($data);
            $invoice->amount_paid = $invoice->invoicePayments()->sum('amount');
            $invoice->status = $invoice->isFullyPaid() ? 'paid' : 'partially_paid';
            $invoice->save();
            $ledger->postInvoicePayment($payment);
        });

        if ($invoice->status === 'paid' && ! $wasPaid) {
            Webhook::trigger($invoice->company_id, 'invoice.paid', $this->webhookPayload($invoice));
        }

        return back()->with('status', __('Payment recorded.'));
    }

    public function storeAttachment(Request $request, Invoice $invoice)
    {
        $request->validate(['file' => ['required', 'file', 'max:10240']]);

        $file = $request->file('file');

        $invoice->attachments()->create([
            'company_id' => $invoice->company_id,
            'uploaded_by' => Auth::id(),
            'original_name' => $file->getClientOriginalName(),
            'path' => $file->store('invoice-attachments', 'public'),
            'size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
        ]);

        return back()->with('status', __('File attached.'));
    }

    public function destroyAttachment(Invoice $invoice, Attachment $attachment)
    {
        abort_unless($attachment->attachable_type === Invoice::class && $attachment->attachable_id === $invoice->id, 404);

        Storage::disk('public')->delete($attachment->path);
        $attachment->delete();

        return back()->with('status', __('Attachment removed.'));
    }

    private function doSend(Invoice $invoice, LedgerPostingService $ledger): void
    {
        if ($invoice->status !== 'draft') {
            return;
        }

        DB::transaction(function () use ($invoice, $ledger) {
            $invoice->update(['status' => 'sent']);

            if ($invoice->warehouse_id && ! $invoice->stock_deducted) {
                $this->applyStock($invoice, -1);
                $invoice->stock_deducted = true;
                $invoice->save();
            }

            $ledger->postInvoiceIssued($invoice);
        });

        AuditLog::record('invoice.send', $invoice, __('Sent invoice :number', ['number' => $invoice->invoice_number]));

        Webhook::trigger($invoice->company_id, 'invoice.sent', $this->webhookPayload($invoice));

        if ($invoice->company->zatca_sync_frequency === 'instant') {
            SyncInvoiceToZatca::dispatch($invoice->id);
        }
    }

    private function webhookPayload(Invoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'status' => $invoice->status,
            'client_id' => $invoice->client_id,
            'total' => $invoice->total,
            'currency' => $invoice->currency,
            'issue_date' => $invoice->issue_date?->toDateString(),
            'due_date' => $invoice->due_date?->toDateString(),
        ];
    }

    private function applyStock(Invoice $invoice, int $direction): void
    {
        $invoice->loadMissing('items.item');

        foreach ($invoice->items as $line) {
            if (! $line->item || ! $line->item->track_inventory) {
                continue;
            }

            $stock = ItemStock::firstOrCreate(
                ['item_id' => $line->item_id, 'warehouse_id' => $invoice->warehouse_id],
                ['quantity' => 0]
            );

            $baseQuantity = $line->item->baseQuantityFor((float) $line->quantity, $line->unit_id);

            $stock->increment('quantity', $baseQuantity * $direction);
        }
    }

    private function syncItems(Invoice $invoice, array $items): void
    {
        foreach ($items as $sort => $row) {
            $line = new InvoiceItem([
                'invoice_id' => $invoice->id,
                'item_id' => $row['item_id'] ?? null,
                'unit_id' => $row['unit_id'] ?? null,
                'description' => $row['description'],
                'quantity' => $row['quantity'],
                'unit_price' => $row['unit_price'],
                'vat_rate' => $row['vat_rate'] ?? TaxRate::defaultRate($invoice->company_id),
                'tax_rate_id' => $row['tax_rate_id'] ?? null,
                'sort_order' => $sort,
            ]);
            $line->recalculate();
            $line->save();
        }
    }

    private function validated(Request $request): array
    {
        $companyId = Auth::user()->company_id;

        return $request->validate([
            'client_id' => ['required', Rule::exists('clients', 'id')->where('company_id', $companyId)],
            'bank_account_id' => ['nullable', Rule::exists('bank_accounts', 'id')->where('company_id', $companyId)],
            'warehouse_id' => ['nullable', Rule::exists('warehouses', 'id')->where('company_id', $companyId)],
            'salesperson_id' => ['nullable', Rule::exists('salespersons', 'id')->where('company_id', $companyId)],
            'project_id' => ['nullable', Rule::exists('projects', 'id')->where('company_id', $companyId)],
            'type' => ['required', 'in:standard,simplified'],
            'issue_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'currency' => ['nullable', 'string', Rule::in(Currencies::codes())],
            'exchange_rate' => ['nullable', 'numeric', 'min:0.000001'],
            'discount_total' => ['nullable', 'numeric', 'min:0'],
            'retention_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['nullable', Rule::exists('items', 'id')->where('company_id', $companyId)],
            'items.*.unit_id' => ['nullable', Rule::exists('units', 'id')->where('company_id', $companyId)],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.vat_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'items.*.tax_rate_id' => ['nullable', Rule::exists('tax_rates', 'id')->where('company_id', $companyId)],
        ]);
    }
}
