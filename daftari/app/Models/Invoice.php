<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\ComputesDiscount;
use App\Services\ZatcaQrGenerator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Invoice extends Model
{
    use BelongsToCompany, ComputesDiscount, SoftDeletes;

    protected $fillable = [
        'company_id', 'client_id', 'quotation_id', 'applies_prepayment_invoice_id', 'branch_id', 'salesperson_id', 'project_id', 'machinery_asset_id', 'created_by', 'invoice_number', 'type',
        'status', 'issue_date', 'due_date', 'subtotal', 'discount_total', 'discount_type', 'discount_value', 'retention_rate', 'retention_amount',
        'vat_total', 'total', 'amount_paid', 'currency', 'exchange_rate', 'notes', 'qr_code', 'bank_account_id', 'warehouse_id', 'stock_deducted',
        'last_reminder_sent_at', 'last_reminder_tier', 'approved_by', 'approved_at', 'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'due_date' => 'date',
            'stock_deducted' => 'boolean',
            'last_reminder_sent_at' => 'datetime',
            'last_reminder_tier' => 'integer',
            'approved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Deliberately not mass-assignable (not in $fillable) — this is
        // the token that makes the public "view & pay" link work, so it
        // must only ever come from the server, never from request input.
        static::creating(function (Invoice $invoice) {
            if (empty($invoice->public_token)) {
                $invoice->public_token = Str::random(40);
            }
        });
    }

    public function isOverdue(): bool
    {
        return in_array($this->status, ['sent', 'partially_paid'], true)
            && $this->due_date
            && $this->due_date->isPast()
            && $this->balanceDue() > 0.0;
    }

    public function daysOverdue(): int
    {
        return $this->due_date ? max(0, (int) $this->due_date->diffInDays(now(), true)) : 0;
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function salesperson(): BelongsTo
    {
        return $this->belongsTo(Salesperson::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    /**
     * The advance (ZATCA type-386) tax invoice this invoice settles, if
     * any — see ZatcaXmlGenerator::generate()'s $appliedPrepayment
     * handling and the add_applies_prepayment_invoice_id_to_invoices_table
     * migration.
     */
    public function appliesPrepaymentInvoice(): BelongsTo
    {
        return $this->belongsTo(PrepaymentInvoice::class, 'applies_prepayment_invoice_id');
    }

    /** The payment-plan stage this invoice was generated for, if any. */
    public function paymentPlanStage(): HasOne
    {
        return $this->hasOne(QuotationPaymentPlanStage::class, 'invoice_id');
    }

    public function machinery(): BelongsTo
    {
        return $this->belongsTo(MachineryAsset::class, 'machinery_asset_id');
    }

    public function installments(): HasMany
    {
        return $this->hasMany(InvoiceInstallment::class)->orderBy('due_date')->orderBy('sort_order');
    }

    /**
     * Audit finding MEDIUM-16: a payment schedule is purely informational
     * — payments still land as one running total against amount_paid (no
     * per-installment allocation/FK), so "paid so far" per installment is
     * derived here by walking the schedule in due-date order and applying
     * amount_paid against each one until it runs out, oldest first.
     */
    public function installmentSchedule(): \Illuminate\Support\Collection
    {
        $remaining = (float) $this->amount_paid;

        return $this->installments->map(function (InvoiceInstallment $installment) use (&$remaining) {
            $paid = min((float) $installment->amount, max(0, $remaining));
            $remaining -= $paid;

            return (object) [
                'installment' => $installment,
                'paid_amount' => round($paid, 2),
                'status' => $paid >= (float) $installment->amount - 0.01
                    ? 'paid'
                    : ($paid > 0 ? 'partial' : 'pending'),
            ];
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function invoicePayments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class);
    }

    public function zatcaInvoiceLogs(): HasMany
    {
        return $this->hasMany(ZatcaInvoiceLog::class);
    }

    public function deliveryNotes(): HasMany
    {
        return $this->hasMany(DeliveryNote::class);
    }

    public function hasUndeliveredItems(): bool
    {
        return $this->items->sum(fn (InvoiceItem $item) => $item->remainingQuantity()) > 0.004;
    }

    public function creditNotes(): HasMany
    {
        return $this->hasMany(CreditNote::class);
    }

    public function debitNotes(): HasMany
    {
        return $this->hasMany(DebitNote::class);
    }

    public function creditedTotal(): float
    {
        return (float) $this->creditNotes()->where('status', 'issued')->sum('total');
    }

    public function debitedTotal(): float
    {
        return (float) $this->debitNotes()->where('status', 'issued')->sum('total');
    }

    public function remainingCreditableTotal(): float
    {
        return round((float) $this->total - $this->creditedTotal(), 2);
    }

    public function advanceCreditApplications(): HasMany
    {
        return $this->hasMany(AdvanceCreditApplication::class);
    }

    public function advanceCreditAppliedTotal(): float
    {
        return (float) $this->advanceCreditApplications()->sum('total');
    }

    /** Partial draw-downs of a genuine 386 Prepayment Invoice against this invoice — see PrepaymentInvoice::applications(). */
    public function prepaymentInvoiceApplications(): HasMany
    {
        return $this->hasMany(PrepaymentInvoiceApplication::class);
    }

    public function prepaymentAppliedTotal(): float
    {
        $fullFk = $this->appliesPrepaymentInvoice ? (float) $this->appliesPrepaymentInvoice->total : 0.0;

        return $fullFk + (float) $this->prepaymentInvoiceApplications()->sum('total');
    }

    /**
     * Mirrors advanceCreditExtraRows() for the genuine, ZATCA-386-backed
     * mechanism — one row for the simple full-amount case
     * (applies_prepayment_invoice_id), plus one per partial
     * PrepaymentInvoiceApplication row.
     */
    public function prepaymentExtraRows(): array
    {
        $rows = [];

        if ($this->appliesPrepaymentInvoice) {
            $rows[] = ['label' => __('Less: Prepayment — :number', ['number' => $this->appliesPrepaymentInvoice->prepayment_number]), 'value' => -$this->appliesPrepaymentInvoice->total, 'variant' => 'red'];
        }

        foreach ($this->prepaymentInvoiceApplications->load('prepaymentInvoice') as $application) {
            $rows[] = ['label' => __('Less: Prepayment — :number', ['number' => $application->prepaymentInvoice->prepayment_number]), 'value' => -$application->total, 'variant' => 'red'];
        }

        return $rows;
    }

    /**
     * "can we do this in our accounting system" — a completed-works
     * invoice crediting part of an earlier advance against itself, line
     * by line, the way a real reconciliation statement shows it:
     * Completed work total / Less: <portion> — <source invoice> / ...
     * Balance after these advance adjustments. Mirrors
     * Quotation::paymentExtraRows()'s own doc comment — rendered via
     * $doc['extra_rows'], the same mechanism already used for every
     * other "Paid" / "Balance due" row, so it shows up on every print
     * layout with no layout-specific work.
     */
    public function advanceCreditExtraRows(): array
    {
        return $this->advanceCreditApplications
            ->load('portion.advanceCredit.sourceInvoice')
            ->map(function (AdvanceCreditApplication $application) {
                $portion = $application->portion;
                $source = $portion->advanceCredit->sourceInvoice;
                $label = __('Less: :portion', ['portion' => $portion->description]);

                if ($source) {
                    $label .= ' — '.$source->invoice_number;
                }

                return ['label' => $label, 'value' => -$application->total, 'variant' => 'red'];
            })
            ->values()
            ->all();
    }

    /**
     * True once ZATCA has cleared (B2B) or reported (B2C) this invoice —
     * at that point the document is part of an immutable tax record and
     * ZATCA no longer allows edits. The only compliant way to correct a
     * locked invoice is a Credit Note referencing it.
     */
    public function isZatcaLocked(): bool
    {
        return $this->zatcaInvoiceLogs()->whereIn('status', ['cleared', 'reported'])->exists();
    }

    /**
     * Why this invoice can't be synced to ZATCA right now, or null if it
     * can — mirrors ZatcaSyncService::pendingInvoices()'s filter criteria
     * for a single invoice, so the invoice page can offer (or explain the
     * absence of) a direct "Sync with ZATCA" action instead of only
     * surfacing eligible invoices in the ZATCA dashboard's pending list.
     */
    public function zatcaSyncBlockedReason(): ?string
    {
        if ($this->isZatcaLocked()) {
            return null;
        }

        $company = $this->company;

        if (! $company->isZatcaOnboarded()) {
            return __('Complete ZATCA onboarding before syncing invoices.');
        }

        if (in_array($this->status, ['draft', 'cancelled'], true)) {
            return __('Send this invoice before it can be synced to ZATCA.');
        }

        if ($this->currency !== $company->currency) {
            return __('ZATCA sync only supports invoices in your base currency (:currency).', ['currency' => $company->currency]);
        }

        if ($this->type === 'standard' && ! $company->zatca_sync_b2b) {
            return __('Standard (B2B) ZATCA sync is turned off in your ZATCA settings.');
        }

        if ($this->type === 'simplified' && ! $company->zatca_sync_b2c) {
            return __('Simplified (B2C) ZATCA sync is turned off in your ZATCA settings.');
        }

        return null;
    }

    public function recalculateTotals(): void
    {
        $items = $this->items;

        $this->subtotal = $items->sum(fn ($item) => $item->quantity * $item->unit_price);
        $this->vat_total = $items->sum('vat_amount');
        $this->discount_total = $this->computeDiscountTotal($this->subtotal);
        $this->total = $this->subtotal - $this->discount_total + $this->vat_total;
        $this->amount_paid = $this->invoicePayments()->sum('amount');

        // The QR's own timestamp (ZATCA business rule KSA-25) must match
        // this document's IssueDate/IssueTime once it's actually submitted
        // — ZatcaXmlGenerator pairs issue_date's calendar date with
        // created_at's real time of day, so the QR has to encode that
        // exact same combination, not issue_date alone (always midnight).
        $issuedAt = $this->issue_date
            ? \Illuminate\Support\Carbon::parse($this->issue_date->format('Y-m-d').' '.($this->created_at ?? now('UTC'))->format('H:i:s'))
            : now();

        $this->qr_code = $this->company->isZatcaQrEnabled()
            ? ZatcaQrGenerator::generate(
                $this->company->name,
                (string) $this->company->vat_number,
                $issuedAt,
                (float) $this->total,
                (float) $this->vat_total
            )
            : null;

        $this->save();
    }

    /**
     * A credit note issued against this invoice reduces what the client
     * actually still owes — it previously didn't, since this only
     * subtracted amount_paid, leaving a credited invoice's displayed
     * balance wrong (the exact CreditNoteController eligibility check,
     * remainingCreditableTotal(), already accounted for credits; this
     * did not).
     */
    public function balanceDue(): float
    {
        return round((float) $this->total - (float) $this->amount_paid - $this->creditedTotal() - $this->advanceCreditAppliedTotal() - $this->prepaymentAppliedTotal(), 2);
    }

    public function isFullyPaid(): bool
    {
        return $this->balanceDue() <= 0.0;
    }

    /**
     * Shared data shape for rendering this invoice through
     * documents.print.pdf — used by InvoiceController::downloadPdf()/
     * emailInvoice(), and by PlatformInvoiceService for the platform's own
     * real ZATCA subscription invoices, so both paths render through the
     * exact same tested template rather than two parallel PDF layouts.
     */
    public function pdfData(): array
    {
        $this->loadMissing('items.item', 'items.taxRate', 'client', 'bankAccount', 'advanceCreditApplications.portion.advanceCredit.sourceInvoice', 'appliesPrepaymentInvoice', 'prepaymentInvoiceApplications.prepaymentInvoice');

        $bankAccount = $this->bankAccount ?? $this->company->defaultBankAccount();

        $doc = [
            'type_label' => $this->type === 'simplified' ? __('Simplified tax invoice') : __('Standard tax invoice'),
            'type_label_ar' => $this->type === 'simplified' ? 'فاتورة ضريبية مبسّطة' : 'فاتورة ضريبية عادية',
            'number' => $this->invoice_number,
            'date_label' => __('Issued'),
            'date' => $this->issue_date,
            // The same instant ZatcaXmlGenerator uses for cbc:IssueTime
            // (and the QR code built from it) — issue_date itself is a
            // date-only column, so this is the only real "issue time"
            // this invoice has.
            'issue_time' => $this->created_at,
            'date2_label' => __('Due'),
            'date2_label_ar' => 'الاستحقاق',
            'date2' => $this->due_date,
            'party_label' => __('Bill to'),
            'party_label_ar' => 'العميل',
            'party' => $this->client,
            'qr_code' => $this->qr_code,
            'zatca_status' => $this->zatcaInvoiceLogs()->whereIn('status', ['cleared', 'reported'])->latest('id')->value('status'),
            'payment_status' => $this->status,
            // Only the sage_classic/retail_classic layouts show this — a
            // single "Payment Type" field makes sense there the way it
            // doesn't for a running itemized payment history (removed
            // from every layout — see the "payment display" fix). The
            // most recent recorded payment's method, or null once nothing
            // has been recorded yet (those layouts simply omit the row).
            'payment_method' => \App\Models\InvoicePayment::methodLabel($this->invoicePayments()->latest('id')->value('method')),
            'lines' => $this->items,
            'currency' => $this->currency,
            'subtotal' => $this->subtotal,
            'discount_total' => $this->discount_total,
            'discount_percent' => $this->discount_type === 'percentage' ? $this->discount_value : null,
            'vat_total' => $this->vat_total,
            'total' => $this->total,
            'extra_rows' => array_merge(
                array_values(array_filter([
                    $this->currency !== $this->company->currency ? [
                        'label' => __(':currency equivalent (rate :rate)', ['currency' => $this->company->currency, 'rate' => rtrim(rtrim(number_format($this->exchange_rate, 6), '0'), '.')]),
                        'value' => round($this->total * $this->exchange_rate, 2),
                        'currency' => $this->company->currency,
                    ] : null,
                    $this->retention_amount > 0 ? [
                        'label' => __('Retention held').' ('.rtrim(rtrim(number_format($this->retention_rate, 2), '0'), '.').'%)',
                        'value' => $this->retention_amount,
                    ] : null,
                ])),
                $this->advanceCreditExtraRows(),
                $this->prepaymentExtraRows(),
                array_values(array_filter([
                    $this->amount_paid > 0 ? ['label' => __('Paid'), 'label_ar' => 'المدفوع', 'value' => $this->amount_paid, 'emphasis' => true, 'variant' => 'green'] : null,
                    \App\Support\Money::balanceRow($this->balanceDue()),
                ])),
            ),
            'bank_account' => $bankAccount,
            'salesperson' => $this->salesperson,
            'notes' => $this->notes,
        ];

        return [
            'doc' => $doc,
            'company' => $this->company,
            'template' => $this->company->defaultTemplateFor('invoice'),
        ];
    }
}
