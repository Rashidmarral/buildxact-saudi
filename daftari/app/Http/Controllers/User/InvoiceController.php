<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Controllers\User\Concerns\EnforcesStorageQuota;
use App\Http\Controllers\User\Concerns\ExportsCsv;
use App\Http\Controllers\User\Concerns\ResolvesPerPage;
use App\Jobs\SyncInvoiceToZatca;
use App\Mail\InvoiceMail;
use App\Models\Attachment;
use App\Models\AuditLog;
use App\Models\BankAccount;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\Project;
use App\Models\Salesperson;
use App\Models\SmsConfig;
use App\Models\TaxRate;
use App\Rules\VatRateMatchesLinkedTaxRate;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Webhook;
use App\Models\WhatsappConfig;
use App\Notifications\GenericNotification;
use App\Services\ApprovalChainService;
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
use Illuminate\Validation\ValidationException;

class InvoiceController extends Controller
{
    use EnforcesStorageQuota, ExportsCsv, ResolvesPerPage;

    /**
     * "2 main users like Ashiq and Rizwan, same company, 2 different
     * business, each generate invoice for their own use — how can i
     * tackle at the end who have what invoices" — a Salesperson tag was
     * already a working field on every invoice (see the form), just with
     * no way to filter by it afterward. Adds that filter plus a
     * count/total/VAT subtotal over exactly what's filtered (issued
     * invoices only — draft/cancelled never counted as real revenue,
     * matching ReportController::vat()'s own exclusion), so each of them
     * can see their own invoices and VAT collected without the other's
     * mixed in.
     */
    public function index(Request $request)
    {
        $query = Invoice::with('client', 'salesperson')
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->when($request->filled('salesperson_id'), fn ($q) => $q->where('salesperson_id', $request->integer('salesperson_id')))
            ->orderByDesc('issue_date')
            ->orderByDesc('id');

        if ($request->query('export') === 'csv') {
            return $this->csvResponse('invoices.csv', $this->csvHeader(), $query->get()->map(fn ($invoice) => $this->csvRow($invoice)));
        }

        $salespersonTotals = null;
        if ($request->filled('salesperson_id')) {
            $counted = (clone $query)->whereNotIn('status', ['draft', 'cancelled'])->get();
            $salespersonTotals = [
                'count' => $counted->count(),
                'total' => (float) $counted->sum('total'),
                'vat' => (float) $counted->sum('vat_total'),
            ];
        }

        $invoices = $query->paginate($this->resolvePerPage($request))->withQueryString();

        return view('user.invoices.index', [
            'invoices' => $invoices,
            'salespersons' => Salesperson::where('is_active', true)->orderBy('name')->get(),
            'salespersonTotals' => $salespersonTotals,
        ]);
    }

    /**
     * Audit finding MEDIUM-15: list pages had no way to act on more than
     * one record at a time. Exports exactly the checked rows (independent
     * of whatever filter is applied), distinct from the existing
     * ?export=csv above which exports everything matching the filter.
     */
    public function bulkExport(Request $request)
    {
        $ids = $this->validatedBulkIds($request);
        $invoices = Invoice::with('client')->whereIn('id', $ids)->orderByDesc('issue_date')->orderByDesc('id')->get();

        return $this->csvResponse('invoices-selected.csv', $this->csvHeader(), $invoices->map(fn ($invoice) => $this->csvRow($invoice)));
    }

    public function bulkDestroy(Request $request)
    {
        $ids = $this->validatedBulkIds($request);
        $invoices = Invoice::whereIn('id', $ids)->get();

        $deleted = 0;

        foreach ($invoices as $invoice) {
            if ($invoice->isZatcaLocked() || $invoice->status !== 'draft') {
                continue;
            }

            $invoice->delete();
            $deleted++;
        }

        $skipped = $invoices->count() - $deleted;

        return back()->with('status', $skipped > 0
            ? __(':deleted deleted, :skipped skipped — only draft, non-ZATCA-locked invoices can be bulk deleted.', ['deleted' => $deleted, 'skipped' => $skipped])
            : __(':deleted invoice(s) deleted.', ['deleted' => $deleted]));
    }

    private function validatedBulkIds(Request $request): array
    {
        return $request->validate(['ids' => ['required', 'array', 'min:1'], 'ids.*' => ['integer']])['ids'];
    }

    private function csvHeader(): array
    {
        return [__('Invoice'), __('Client'), __('Date'), __('Total'), __('Balance due'), __('Status')];
    }

    private function csvRow(Invoice $invoice): array
    {
        return [
            $invoice->invoice_number,
            $invoice->client->name,
            $invoice->issue_date->format('Y-m-d'),
            number_format($invoice->total, 2),
            number_format($invoice->balanceDue(), 2),
            $invoice->status,
        ];
    }

    public function create()
    {
        $company = Auth::user()->company;

        return view('user.invoices.form', [
            'invoice' => new Invoice(['issue_date' => now()->toDateString(), 'due_date' => now()->addDays(30)->toDateString(), 'currency' => $company->currency, 'exchange_rate' => 1]),
            'clients' => Client::orderBy('name')->get(),
            'items' => Item::where('is_active', true)->with('baseUnit', 'itemUnits.unit')->orderBy('name')->get(),
            'units' => Unit::orderBy('name')->get(),
            'salespersons' => Salesperson::where('is_active', true)->orderBy('name')->get(),
            'projects' => Project::orderBy('name')->get(),
            'bankAccounts' => BankAccount::where('is_active', true)->where('is_personal', false)->orderBy('name')->get(),
            'warehouses' => Warehouse::orderBy('name')->get(),
            'currencies' => Currencies::catalog(),
            'availablePrepaymentInvoices' => \App\Models\PrepaymentInvoice::with('client')
                ->where('status', 'issued')
                ->whereDoesntHave('appliedToInvoice')
                ->orderByDesc('issue_date')
                ->get(),
            'nextNumberPreview' => $company->invoice_prefix.'-'.str_pad((string) $company->next_invoice_number, 5, '0', STR_PAD_LEFT),
            'defaultPaymentTermsDays' => $company->default_payment_terms_days ?? 30,
        ]);
    }

    public function store(Request $request, LedgerPostingService $ledger)
    {
        if (Auth::user()->company->hasReachedPlanLimit('invoices')) {
            return back()->withErrors(['plan_limit' => __('You have reached your plan\'s monthly invoice limit. Upgrade your plan to create more invoices this billing period.')])->withInput();
        }

        $data = $this->validated($request);
        $sendImmediately = $request->boolean('send_immediately');
        $paymentStatus = $data['payment_status'] ?? 'unpaid';
        // A cash-sale-style invoice ("this was already paid when I wrote
        // it") needs a real InvoicePayment recorded, not just a label.
        $wantsPayment = in_array($paymentStatus, ['paid', 'partially_paid'], true);

        $invoice = DB::transaction(function () use ($data, $paymentStatus) {
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
                'applies_prepayment_invoice_id' => $data['applies_prepayment_invoice_id'] ?? null,
                'created_by' => Auth::id(),
                'invoice_number' => $company->nextInvoiceNumber(),
                'type' => $data['type'],
                'status' => 'draft',
                'issue_date' => $data['issue_date'],
                'due_date' => $data['due_date'] ?? null,
                'discount_type' => $data['discount_type'] ?? 'fixed',
                'discount_value' => $data['discount_value'] ?? 0,
                'retention_rate' => $data['retention_rate'] ?? 0,
                'retention_amount' => $retentionAmount,
                'currency' => $data['currency'] ?? $company->currency,
                'exchange_rate' => ($data['currency'] ?? $company->currency) === $company->currency ? 1 : ($data['exchange_rate'] ?? 1),
                'notes' => $data['notes'] ?? null,
            ]);

            $this->syncItems($invoice, $data['items']);
            $invoice->recalculateTotals();

            // The real total (vat_amount per line, discount, retention)
            // only exists once items are synced and totaled above, so a
            // "partially paid" amount can only be bounds-checked against
            // it here — throwing rolls back the whole invoice+items
            // insert (DB::transaction rethrows after rollback) and lands
            // the user back on the form with this error, same as any
            // other validation failure.
            if ($paymentStatus === 'partially_paid') {
                $amountPaid = (float) ($data['amount_paid'] ?? 0);
                if ($amountPaid <= 0 || $amountPaid >= (float) $invoice->total) {
                    throw ValidationException::withMessages([
                        'amount_paid' => __('Amount paid must be greater than zero and less than the invoice total of :total.', ['total' => \App\Support\Money::format($invoice->total)]),
                    ]);
                }
            }

            return $invoice;
        });

        AuditLog::record('invoice.create', $invoice, __('Created invoice :number', ['number' => $invoice->invoice_number]));

        Webhook::trigger($invoice->company_id, 'invoice.created', $this->webhookPayload($invoice));

        if (($sendImmediately || $wantsPayment) && $invoice->client->credit_hold) {
            return redirect()->route('app.invoices.show', $invoice)->withErrors(['invoice' => __('This client is on credit hold and cannot be sent new invoices (:reason). The invoice was saved as a draft — release the hold on the client record first.', ['reason' => $invoice->client->credit_hold_reason ?: __('no reason given')])]);
        }

        // Recording a payment requires the invoice to actually be sent
        // first — same reasoning as storePayment()'s own doSend() call:
        // a payment against a still-draft invoice would jump straight to
        // paid/partially_paid with its revenue never posted to the GL.
        // requestSend() (not doSend() directly) still routes through an
        // approval chain when the company has one configured, so a
        // large invoice isn't silently marked paid ahead of its own
        // approval.
        if ($sendImmediately || $wantsPayment) {
            $this->requestSend($invoice, $ledger);
            $invoice = $invoice->fresh();
        }

        if ($wantsPayment && $invoice->status === 'sent') {
            $amount = $paymentStatus === 'paid' ? (float) $invoice->total : (float) $data['amount_paid'];

            DB::transaction(function () use ($invoice, $amount, $data, $ledger) {
                $payment = $invoice->invoicePayments()->create([
                    'amount' => $amount,
                    'paid_at' => $invoice->issue_date,
                    'method' => $data['payment_mode'] ?? null,
                    // Only attach the invoice's own "Bank details" account
                    // when the money actually moved through a bank — a
                    // cash/card/other payment didn't land in that account,
                    // and wrongly crediting it would misstate that
                    // account's own statement and Project Cash Flow.
                    'bank_account_id' => ($data['payment_mode'] ?? null) === 'bank_transfer' ? $invoice->bank_account_id : null,
                ]);
                $invoice->amount_paid = $invoice->invoicePayments()->sum('amount');
                $invoice->status = $invoice->isFullyPaid() ? 'paid' : 'partially_paid';
                $invoice->save();
                $ledger->postInvoicePayment($payment);
            });

            AuditLog::record('invoice.payment_record', $invoice, __('Recorded a payment of :amount :currency on invoice :number', [
                'amount' => number_format($amount, 2), 'currency' => $invoice->currency, 'number' => $invoice->invoice_number,
            ]));

            if ($invoice->status === 'paid') {
                Webhook::trigger($invoice->company_id, 'invoice.paid', $this->webhookPayload($invoice));
            }
        }

        $status = match (true) {
            $wantsPayment && $invoice->status === 'paid' => __('Invoice created, sent, and marked paid.'),
            $wantsPayment && $invoice->status === 'partially_paid' => __('Invoice created, sent, with a partial payment recorded.'),
            $wantsPayment && $invoice->status === 'pending_approval' => __('Invoice created and submitted for approval — record the payment once it\'s approved and sent.'),
            $sendImmediately && $invoice->status === 'pending_approval' => __('Invoice created and submitted for approval.'),
            $sendImmediately => __('Invoice created and sent.'),
            default => __('Invoice created.'),
        };

        return redirect()->route('app.invoices.show', $invoice)->with('status', $status);
    }

    public function show(Invoice $invoice)
    {
        $invoice->load('items.item', 'items.taxRate', 'client', 'invoicePayments.bankAccount', 'bankAccount', 'warehouse', 'attachments', 'installments', 'advanceCreditApplications.portion.advanceCredit.sourceInvoice', 'appliesPrepaymentInvoice', 'prepaymentInvoiceApplications.prepaymentInvoice');
        $template = $invoice->company->defaultTemplateFor('invoice');
        $whatsappEnabled = (bool) WhatsappConfig::where('is_enabled', true)->exists();
        $smsEnabled = (bool) SmsConfig::where('is_enabled', true)->exists();
        $paymentBankAccounts = BankAccount::where('is_active', true)->orderBy('name')->get();
        $availableAdvancePortions = \App\Models\AdvanceCreditPortion::with('advanceCredit.sourceInvoice')
            ->whereHas('advanceCredit', fn ($q) => $q->where('client_id', $invoice->client_id))
            ->get()
            ->filter(fn (\App\Models\AdvanceCreditPortion $portion) => $portion->remainingTotal() > 0.009)
            ->values();
        $availablePrepaymentInvoices = \App\Models\PrepaymentInvoice::where('client_id', $invoice->client_id)
            ->where('status', 'issued')
            ->whereDoesntHave('appliedToInvoice')
            ->get()
            ->filter(fn (\App\Models\PrepaymentInvoice $prepayment) => $prepayment->remainingTotal() > 0.009)
            ->values();

        return view('user.invoices.show', compact('invoice', 'template', 'whatsappEnabled', 'smsEnabled', 'paymentBankAccounts', 'availableAdvancePortions', 'availablePrepaymentInvoices'));
    }

    public function downloadPdf(Invoice $invoice, MpdfRenderer $renderer)
    {
        $pdf = $renderer->render('documents.print.pdf', $invoice->pdfData());

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

        if ($blocked = $this->blockedByMissingClearance($invoice)) {
            return $blocked;
        }

        $pdfBinary = $renderer->render('documents.print.pdf', $invoice->pdfData());

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

        // Audit finding CRITICAL-1: update() rebuilt the document from
        // scratch (items, totals) but never touched the ledger entry or
        // deducted stock that send() had already posted — editing a sent/
        // paid invoice silently desynced its journal entry and inventory
        // from what the invoice itself now showed. Rather than build a
        // full delete-and-repost (which also has to re-derive stock deltas
        // across a warehouse/quantity change), editing is restricted to
        // drafts — exactly how destroy() already treats non-draft invoices,
        // and how Bills/Credit Notes/Debit Notes/Purchase Returns in this
        // codebase have no edit action at all once posted.
        if ($invoice->status !== 'draft') {
            return redirect()->route('app.invoices.show', $invoice)
                ->withErrors(['invoice' => __('Only draft invoices can be edited — this invoice has already been sent and posted to the ledger. Cancel it to correct it, or issue a credit note if it has been paid.')]);
        }

        $invoice->load('items');

        return view('user.invoices.form', [
            'invoice' => $invoice,
            'clients' => Client::orderBy('name')->get(),
            'items' => Item::where('is_active', true)->with('baseUnit', 'itemUnits.unit')->orderBy('name')->get(),
            'units' => Unit::orderBy('name')->get(),
            'salespersons' => Salesperson::where('is_active', true)->orderBy('name')->get(),
            'projects' => Project::orderBy('name')->get(),
            'bankAccounts' => BankAccount::where('is_active', true)->where('is_personal', false)->orderBy('name')->get(),
            'warehouses' => Warehouse::orderBy('name')->get(),
            'currencies' => Currencies::catalog(),
            'defaultPaymentTermsDays' => $invoice->company->default_payment_terms_days ?? 30,
        ]);
    }

    public function update(Request $request, Invoice $invoice)
    {
        if ($invoice->isZatcaLocked()) {
            return redirect()->route('app.invoices.show', $invoice)
                ->withErrors(['invoice' => __('This invoice has been cleared/reported to ZATCA and is now part of an immutable tax record — it can no longer be edited. Issue a credit note to correct it instead.')]);
        }

        // See edit() — must also be enforced here, not just when loading the
        // form, since this action can be reached directly.
        if ($invoice->status !== 'draft') {
            return redirect()->route('app.invoices.show', $invoice)
                ->withErrors(['invoice' => __('Only draft invoices can be edited — this invoice has already been sent and posted to the ledger. Cancel it to correct it, or issue a credit note if it has been paid.')]);
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
                'discount_type' => $data['discount_type'] ?? 'fixed',
                'discount_value' => $data['discount_value'] ?? 0,
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

        // Security audit finding M-16: draft invoice edits went completely
        // unlogged (every status-change action already is).
        AuditLog::record('invoice.update', $invoice, __('Updated draft invoice :number', ['number' => $invoice->invoice_number]));

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

        $number = $invoice->invoice_number;
        $invoice->delete();

        // Security audit finding M-16: also unlogged before — the
        // company's own Activity page had no record of a draft invoice
        // being permanently removed.
        AuditLog::record('invoice.delete', null, __('Deleted draft invoice :number', ['number' => $number]), companyId: $invoice->company_id);

        return redirect()->route('app.invoices.index')->with('status', __('Invoice deleted.'));
    }

    public function send(Invoice $invoice, LedgerPostingService $ledger)
    {
        if ($blocked = $this->blockedByCreditHold($invoice)) {
            return $blocked;
        }

        $this->requestSend($invoice, $ledger);

        return back()->with('status', $invoice->fresh()->status === 'pending_approval'
            ? __('Invoice submitted for approval.')
            : __('Invoice marked as sent.'));
    }

    /**
     * "nothing stops you from issuing a new invoice to someone who's
     * badly overdue" — a client flagged credit_hold (see
     * CollectionNoteController::toggleHold()) can still have a draft
     * invoice drafted for later review, but it can't actually be sent
     * until the hold is released on the client record. Returns a
     * response to short-circuit the caller, or null when unblocked.
     */
    private function blockedByCreditHold(Invoice $invoice): ?\Illuminate\Http\RedirectResponse
    {
        if (! $invoice->client->credit_hold) {
            return null;
        }

        return back()->withErrors(['invoice' => __('This client is on credit hold and cannot be sent new invoices (:reason). Release the hold on the client record first.', ['reason' => $invoice->client->credit_hold_reason ?: __('no reason given')])]);
    }

    /**
     * Audit finding ZATCA-01: a standard (B2B) tax invoice is regulated
     * under ZATCA's Clearance Model — the government must cryptographically
     * clear it BEFORE it reaches the buyer, unlike a simplified (B2C) one,
     * which is reported only after the fact. Marking an invoice "sent" in
     * this app (doSend()) only ever queues the actual ZATCA submission
     * (instant sync is an async job; anything else waits for a batched
     * sync run) — nothing stopped the literal document handoff to the
     * customer (this email) from happening well before that submission
     * ever completes, or even ran at all. Only blocks once the company has
     * actually turned on B2B sync and finished onboarding; a company that
     * hasn't reached that point yet isn't under this obligation in the
     * first place, so it isn't newly blocked from emailing invoices.
     */
    private function blockedByMissingClearance(Invoice $invoice): ?\Illuminate\Http\RedirectResponse
    {
        $company = $invoice->company;

        if ($invoice->type !== 'standard' || ! $company->zatca_sync_b2b || ! $company->isZatcaOnboarded()) {
            return null;
        }

        if ($invoice->zatcaInvoiceLogs()->where('status', 'cleared')->exists()) {
            return null;
        }

        return back()->withErrors(['invoice' => __('This standard tax invoice has not yet been cleared by ZATCA — it must be cleared before it can be delivered to the customer. Sync it from the ZATCA dashboard, or wait for the next scheduled sync, then try again.')]);
    }

    /**
     * Cancelling an already-sent invoice (or voiding the credit note that
     * corrects one — see CreditNoteController::void()) isn't itself a
     * sequential multi-person approval the way submitting one for review
     * is, so this doesn't create ApprovalProgress rows — it's a one-shot
     * "the most senior configured role must sign off" gate, only once a
     * company has actually configured an invoice chain (Settings →
     * Approvals). A company with no chain configured keeps today's
     * behavior unchanged (any user with the "invoices" permission can
     * cancel). Returns a response to short-circuit the caller, or null
     * when unblocked.
     */
    private function blockedByMissingSeniorApproval(): ?\Illuminate\Http\RedirectResponse
    {
        $chain = app(ApprovalChainService::class);
        $seniorStep = $chain->highestConfiguredStep('invoice');

        if (! $seniorStep) {
            return null;
        }

        if ($chain->userHoldsStepRole($seniorStep, Auth::user())) {
            return null;
        }

        return back()->withErrors(['invoice' => __('Cancelling an invoice requires :role sign-off.', ['role' => $seniorStep->role?->name])]);
    }

    public function approve(Invoice $invoice, LedgerPostingService $ledger)
    {
        abort_unless($invoice->status === 'pending_approval', 404);

        $chain = app(ApprovalChainService::class);
        $currentStep = $chain->currentStep($invoice);

        if ($currentStep) {
            // A company with a configured invoice chain: the current
            // step's role (or the owner) must sign off, and a non-final
            // step just advances the chain — mirrors
            // PurchaseOrderController::approve().
            abort_unless($chain->canActOn($currentStep, Auth::user()), 403);

            if (! $chain->approveStep($currentStep, Auth::user())) {
                $chain->notifyStepApprovers($chain->currentStep($invoice), fn (User $user) => $user->notify(new GenericNotification(
                    title: __('Invoice awaiting your approval'),
                    body: __(':amount :currency for :client', ['amount' => number_format((float) $invoice->total, 2), 'currency' => $invoice->currency, 'client' => $invoice->client->name]),
                    url: route('app.invoices.show', $invoice),
                    icon: 'invoices',
                )));

                return back()->with('status', __('Approved — waiting on the next approver.'));
            }
        } else {
            // No chain configured for this company — the older flat
            // "approvals" permission gate, unchanged.
            abort_unless(Auth::user()->hasPermission('approvals'), 403);
        }

        $invoice->update(['status' => 'draft', 'approved_by' => Auth::id(), 'approved_at' => now()]);
        $this->doSend($invoice, $ledger);

        AuditLog::record('invoice.approve', $invoice, __('Approved invoice :number', ['number' => $invoice->invoice_number]));

        return back()->with('status', __('Invoice approved and sent.'));
    }

    public function reject(Request $request, Invoice $invoice)
    {
        abort_unless($invoice->status === 'pending_approval', 404);

        $chain = app(ApprovalChainService::class);
        $currentStep = $chain->currentStep($invoice);

        if ($currentStep) {
            abort_unless($chain->canActOn($currentStep, Auth::user()), 403);
        } else {
            abort_unless(Auth::user()->hasPermission('approvals'), 403);
        }

        $data = $request->validate(['rejection_reason' => ['nullable', 'string', 'max:1000']]);

        if ($currentStep) {
            $chain->rejectCurrent($invoice, Auth::user(), $data['rejection_reason'] ?? null);
        }

        $invoice->update([
            'status' => 'draft',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
            'rejection_reason' => $data['rejection_reason'] ?? null,
        ]);

        AuditLog::record('invoice.reject', $invoice, __('Rejected invoice :number', ['number' => $invoice->invoice_number]));

        if ($invoice->created_by) {
            User::find($invoice->created_by)?->notify(new GenericNotification(
                title: __('Invoice rejected'),
                body: __('Invoice :number was returned to draft for revision.', ['number' => $invoice->invoice_number]),
                url: route('app.invoices.show', $invoice),
                icon: 'invoices',
            ));
        }

        return back()->with('status', __('Invoice rejected — returned to draft.'));
    }

    public function cancel(Invoice $invoice, LedgerPostingService $ledger)
    {
        if (in_array($invoice->status, ['draft', 'cancelled'], true)) {
            return back()->withErrors(['invoice' => __('This invoice cannot be cancelled.')]);
        }

        if ($invoice->isZatcaLocked()) {
            return back()->withErrors(['invoice' => __('This invoice has been cleared/reported to ZATCA and cannot be cancelled directly — issue a credit note instead, which correctly notifies ZATCA of the adjustment.')]);
        }

        if ($blocked = $this->blockedByMissingSeniorApproval()) {
            return $blocked;
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
            // Which real account the money landed in — without this, a
            // genuinely received payment was invisible on that account's
            // own statement and on the Project Cash Flow page (see
            // ProjectCashFlowController::buildLedger(), which never
            // queried InvoicePayment at all). Optional for backward
            // compatibility with payments recorded before this existed.
            'bank_account_id' => ['nullable', Rule::exists('bank_accounts', 'id')->where('company_id', $invoice->company_id)],
            // Only meaningful when the invoice's currency differs from the
            // company's — the rate actually in effect on the day the money
            // was banked, which can differ from the invoice's own booked
            // rate. Left blank (or on a same-currency invoice) it falls
            // back to the invoice's rate in postInvoicePayment(), so no FX
            // gain/loss is recognized unless a real rate was given.
            'exchange_rate' => ['nullable', 'numeric', 'min:0.000001'],
        ]);

        // "1 document(s) are marked posted but have no matching ledger
        // entry" (Company Audit's ledger-integrity check) — a draft
        // invoice could have a payment recorded against it straight from
        // this form, jumping its status to paid/partially_paid with its
        // revenue never recognized in the GL at all: real cash coming in
        // debited nothing against an AR balance that was never credited.
        // Sending it first (a no-op if it's already past draft, per
        // doSend()'s own guard) keeps the books consistent with the fact
        // that money changing hands means the invoice really was issued.
        $this->doSend($invoice, $ledger);

        $wasPaid = $invoice->status === 'paid';

        DB::transaction(function () use ($invoice, $data, $ledger) {
            $payment = $invoice->invoicePayments()->create($data);
            $invoice->amount_paid = $invoice->invoicePayments()->sum('amount');
            $invoice->status = $invoice->isFullyPaid() ? 'paid' : 'partially_paid';
            $invoice->save();
            $ledger->postInvoicePayment($payment);
        });

        // Security audit finding M-16: payment recording went completely
        // unlogged — real money moving against an invoice had no trace on
        // the company's own Activity page.
        AuditLog::record('invoice.payment_record', $invoice, __('Recorded a payment of :amount :currency on invoice :number', [
            'amount' => number_format((float) $data['amount'], 2), 'currency' => $invoice->currency, 'number' => $invoice->invoice_number,
        ]));

        if ($invoice->status === 'paid' && ! $wasPaid) {
            Webhook::trigger($invoice->company_id, 'invoice.paid', $this->webhookPayload($invoice));
        }

        return back()->with('status', __('Payment recorded.'));
    }

    /**
     * Corrects a previously-recorded payment — most commonly to attach
     * the real bank_account_id to one recorded before that field existed
     * (see storePayment()'s docblock), but any field can be fixed here.
     * Rebuilds its journal entry from scratch (deletePosting + repost,
     * same pattern as FixedAssetLifecycleService::amend()) rather than
     * reverse()'s audit-trail-preserving approach, since this is an
     * in-place correction, not a cancellation — and recomputes the
     * invoice's own amount_paid/status in case the amount changed.
     */
    public function updatePayment(Request $request, Invoice $invoice, InvoicePayment $payment, LedgerPostingService $ledger)
    {
        abort_unless($payment->invoice_id === $invoice->id, 404);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:'.max($invoice->balanceDue() + (float) $payment->amount, 0.01)],
            'paid_at' => ['required', 'date'],
            'method' => ['nullable', 'string', 'max:30'],
            'reference' => ['nullable', 'string', 'max:255'],
            'bank_account_id' => ['nullable', Rule::exists('bank_accounts', 'id')->where('company_id', $invoice->company_id)],
            'exchange_rate' => ['nullable', 'numeric', 'min:0.000001'],
        ]);

        DB::transaction(function () use ($invoice, $payment, $data, $ledger) {
            $ledger->deletePosting($invoice->company, 'invoice_payment', $payment->id);
            $payment->update($data);
            $invoice->amount_paid = $invoice->invoicePayments()->sum('amount');
            $invoice->status = $invoice->isFullyPaid() ? 'paid' : ($invoice->amount_paid > 0 ? 'partially_paid' : 'sent');
            $invoice->save();
            $ledger->postInvoicePayment($payment->fresh());
        });

        AuditLog::record('invoice.payment_update', $invoice, __('Updated a payment of :amount :currency on invoice :number', [
            'amount' => number_format((float) $data['amount'], 2), 'currency' => $invoice->currency, 'number' => $invoice->invoice_number,
        ]));

        return back()->with('status', __('Payment updated.'));
    }

    /**
     * Audit finding MEDIUM-16: lets a company split an invoice's total
     * into a scheduled payment plan (e.g. deposit + balance, or monthly
     * installments) shown to the client on both the internal show page and
     * the public pay link. Deliberately informational only — payments
     * still land as one running total against amount_paid the same as
     * before (see Invoice::installmentSchedule(), which derives paid/
     * pending per line from that total); this doesn't touch how a payment
     * is recorded or which gateway checkout amount is charged.
     */
    public function storeInstallments(Request $request, Invoice $invoice)
    {
        $data = $request->validate([
            'installments' => ['required', 'array', 'min:2'],
            'installments.*.description' => ['nullable', 'string', 'max:255'],
            'installments.*.due_date' => ['required', 'date'],
            'installments.*.amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        $sum = round(collect($data['installments'])->sum('amount'), 2);

        if (abs($sum - round((float) $invoice->total, 2)) > 0.01) {
            return back()->withErrors(['installments' => __('The installment amounts must add up to the invoice total (:total).', ['total' => \App\Support\Money::format($invoice->total)])])->withInput();
        }

        DB::transaction(function () use ($invoice, $data) {
            $invoice->installments()->delete();

            foreach ($data['installments'] as $sort => $row) {
                $invoice->installments()->create([
                    'description' => $row['description'] ?? null,
                    'due_date' => $row['due_date'],
                    'amount' => $row['amount'],
                    'sort_order' => $sort,
                ]);
            }
        });

        AuditLog::record('invoice.installments_set', $invoice, __('Set a :count-installment payment schedule on invoice :number', ['count' => count($data['installments']), 'number' => $invoice->invoice_number]));

        return back()->with('status', __('Payment schedule saved.'));
    }

    public function destroyInstallments(Invoice $invoice)
    {
        $invoice->installments()->delete();

        AuditLog::record('invoice.installments_cleared', $invoice, __('Removed the payment schedule from invoice :number', ['number' => $invoice->invoice_number]));

        return back()->with('status', __('Payment schedule removed.'));
    }

    public function storeAttachment(Request $request, Invoice $invoice)
    {
        if ($rejected = $this->rejectIfStorageQuotaReached($invoice->company)) {
            return $rejected;
        }

        $request->validate(['file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,gif,webp,doc,docx,xls,xlsx,csv,txt', 'max:10240']]);

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

    /**
     * The gate every "send this invoice" path funnels through: above the
     * company's invoice approval threshold, this parks it in
     * pending_approval and notifies approvers instead of actually sending
     * it — doSend() only ever runs once that's cleared (or was never
     * required).
     */
    private function requestSend(Invoice $invoice, LedgerPostingService $ledger): void
    {
        if ($invoice->status !== 'draft') {
            return;
        }

        $chain = app(ApprovalChainService::class);

        if ($chain->isConfigured('invoice')) {
            $steps = $chain->applicableSteps('invoice', (float) $invoice->total);

            if ($steps->isEmpty()) {
                $this->doSend($invoice, $ledger);

                return;
            }

            $invoice->update(['status' => 'pending_approval']);
            $chain->start($invoice, $steps);
            $chain->notifyStepApprovers($chain->currentStep($invoice), fn (User $user) => $user->notify(new GenericNotification(
                title: __('Invoice awaiting your approval'),
                body: __(':amount :currency for :client', ['amount' => number_format((float) $invoice->total, 2), 'currency' => $invoice->currency, 'client' => $invoice->client->name]),
                url: route('app.invoices.show', $invoice),
                icon: 'invoices',
            )));
            AuditLog::record('invoice.submit_for_approval', $invoice, __('Submitted invoice :number for approval', ['number' => $invoice->invoice_number]));

            return;
        }

        if ($invoice->company->invoiceRequiresApproval((float) $invoice->total)) {
            $invoice->update(['status' => 'pending_approval']);
            $this->notifyApprovers($invoice);
            AuditLog::record('invoice.submit_for_approval', $invoice, __('Submitted invoice :number for approval', ['number' => $invoice->invoice_number]));

            return;
        }

        $this->doSend($invoice, $ledger);
    }

    private function notifyApprovers(Invoice $invoice): void
    {
        User::where('company_id', $invoice->company_id)
            ->get()
            ->filter(fn (User $user) => $user->hasPermission('approvals'))
            ->each(fn (User $user) => $user->notify(new GenericNotification(
                title: __('Invoice awaiting approval'),
                body: __(':amount :currency for :client', ['amount' => number_format($invoice->total, 2), 'currency' => $invoice->currency, 'client' => $invoice->client->name]),
                url: route('app.invoices.show', $invoice),
                icon: 'invoices',
            )));
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
        $invoice->loadMissing('items.item.kitComponents.componentItem');

        foreach ($invoice->items as $line) {
            if (! $line->item) {
                continue;
            }

            $baseQuantity = $line->item->baseQuantityFor((float) $line->quantity, $line->unit_id);

            // A kit never carries its own ItemStock row — selling one
            // deducts each of its components instead, scaled by how many
            // of that component one unit of the kit contains.
            if ($line->item->is_kit) {
                foreach ($line->item->kitComponents as $component) {
                    if (! $component->componentItem?->track_inventory) {
                        continue;
                    }

                    $this->adjustStock($component->component_item_id, $invoice->warehouse_id, (float) $component->quantity * $baseQuantity * $direction);
                }

                continue;
            }

            if (! $line->item->track_inventory) {
                continue;
            }

            $this->adjustStock($line->item_id, $invoice->warehouse_id, $baseQuantity * $direction);
        }
    }

    private function adjustStock(int $itemId, ?int $warehouseId, float $delta): void
    {
        $stock = ItemStock::firstOrCreate(
            ['item_id' => $itemId, 'warehouse_id' => $warehouseId],
            ['quantity' => 0]
        );

        $stock->increment('quantity', $delta);
    }

    private function syncItems(Invoice $invoice, array $items): void
    {
        // array_values() re-ranks by submission order rather than the
        // original items[N] index — the line-item table supports drag
        // reordering, which moves a row's DOM position without renaming
        // its input names, so sort_order must come from where the row
        // landed, not the index it was first created with.
        foreach (array_values($items) as $sort => $row) {
            // Snapshot the item's Arabic name/description at the moment
            // this line is created — the print templates read these frozen
            // columns rather than the live Item, so editing the product
            // later doesn't retroactively rewrite an already-issued invoice.
            $item = ! empty($row['item_id']) ? Item::find($row['item_id']) : null;

            $line = new InvoiceItem([
                'invoice_id' => $invoice->id,
                'item_id' => $row['item_id'] ?? null,
                'unit_id' => $row['unit_id'] ?? null,
                'description' => $row['description'],
                'name_ar' => $item?->name_ar,
                'item_description' => $item?->description,
                'quantity' => $row['quantity'],
                'unit_price' => $row['unit_price'],
                // Audit finding INV-04: freeze this item's cost as of right
                // now, the same way name_ar/item_description above are
                // frozen — so a profitability report for this sale later
                // reads back what it actually cost at the time, not
                // whatever the item's cost has since changed to.
                'unit_cost' => $item?->purchasePriceForUnit($row['unit_id'] ?? null),
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
            // Settles an earlier advance (ZATCA type-386 prepayment
            // invoice) — see ZatcaXmlGenerator::generate()'s
            // $appliedPrepayment handling. Must belong to this company,
            // this same client, be issued (not draft/cancelled), and not
            // already applied to a different invoice — all three are
            // business rules a bare Rule::exists() can't express, so
            // they're checked together in one closure for one clear
            // error message.
            'applies_prepayment_invoice_id' => ['nullable', Rule::exists('prepayment_invoices', 'id')->where('company_id', $companyId), function ($attribute, $value, $fail) use ($request, $companyId) {
                if (! $value) {
                    return;
                }
                $prepayment = \App\Models\PrepaymentInvoice::where('company_id', $companyId)->find($value);
                if (! $prepayment || $prepayment->status !== 'issued') {
                    $fail(__('This prepayment invoice is not available to apply — it must be issued (not draft/cancelled).'));
                } elseif ((string) $prepayment->client_id !== (string) $request->input('client_id')) {
                    $fail(__('This prepayment invoice belongs to a different customer than the one selected for this invoice.'));
                } elseif ($prepayment->isApplied()) {
                    $fail(__('This prepayment invoice has already been applied to another invoice.'));
                }
            }],
            'type' => ['required', 'in:standard,simplified'],
            'issue_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'currency' => ['nullable', 'string', Rule::in(Currencies::codes())],
            'exchange_rate' => ['nullable', 'numeric', 'min:0.000001'],
            'discount_type' => ['nullable', 'in:fixed,percentage'],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'retention_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            // Only meaningful at creation (the create form is the only
            // place these are submitted from — see store()); 'paid'/
            // 'partially_paid' record a real InvoicePayment, not just a
            // cosmetic label, so the upper-bound check against the
            // invoice's real total (only known once items are totaled)
            // happens inside store() itself, not here.
            'payment_mode' => ['nullable', 'in:cash,bank_transfer,card,other'],
            'payment_status' => ['nullable', 'in:unpaid,paid,partially_paid'],
            'amount_paid' => ['nullable', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['nullable', Rule::exists('items', 'id')->where('company_id', $companyId)],
            'items.*.unit_id' => ['nullable', Rule::exists('units', 'id')->where('company_id', $companyId)],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            // Security audit finding M-02: when a tax_rate_id is present,
            // the numeric vat_rate submitted alongside it must actually
            // match that tax rate's real percentage.
            'items.*.vat_rate' => ['required', 'numeric', 'min:0', 'max:100', new VatRateMatchesLinkedTaxRate],
            'items.*.tax_rate_id' => ['nullable', Rule::exists('tax_rates', 'id')->where('company_id', $companyId)],
        ]);
    }
}
