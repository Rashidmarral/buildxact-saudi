<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\ComputesDiscount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Quotation extends Model
{
    use BelongsToCompany, ComputesDiscount, SoftDeletes;

    protected $fillable = [
        'company_id', 'client_id', 'project_id', 'branch_id', 'salesperson_id', 'created_by', 'converted_invoice_id',
        'quotation_number', 'type', 'status', 'is_staged', 'issue_date', 'expiry_date', 'subtotal', 'discount_total', 'discount_type', 'discount_value',
        'vat_total', 'total', 'advance_received', 'currency', 'notes', 'bank_account_id',
        'approved_by', 'approved_at', 'approval_rejection_reason',
        'accepted_at', 'accepted_by_name', 'accepted_signature', 'accepted_ip',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'expiry_date' => 'date',
            'approved_at' => 'datetime',
            'accepted_at' => 'datetime',
            'is_staged' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // The token that makes the client-portal "view & accept" link work
        // — deliberately not mass-assignable, must only ever come from the
        // server, mirroring Invoice::public_token.
        static::creating(function (Quotation $quotation) {
            if (empty($quotation->public_token)) {
                $quotation->public_token = Str::random(40);
            }
        });
    }

    /**
     * Whether a client should be able to see this on their public link/
     * portal at all — a draft or pending-approval quotation hasn't been
     * issued to them yet.
     */
    public function isPubliclyViewable(): bool
    {
        return ! in_array($this->status, ['draft', 'pending_approval'], true);
    }

    /**
     * Whether the client can still accept/reject it — once converted,
     * expired, or already decided, the decision is final.
     */
    public function isActionable(): bool
    {
        return $this->status === 'issued' && ! $this->isExpired();
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function salesperson(): BelongsTo
    {
        return $this->belongsTo(Salesperson::class);
    }

    public function convertedInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'converted_invoice_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class);
    }

    /** Every invoice generated from this quotation's payment-plan stages. */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function paymentPlanStages(): HasMany
    {
        return $this->hasMany(QuotationPaymentPlanStage::class)->orderBy('sort_order');
    }

    /**
     * Locked once the first stage is actually invoiced — redefining the
     * plan after that would orphan the stage a real Invoice already
     * points back to.
     */
    public function paymentPlanIsLocked(): bool
    {
        return $this->paymentPlanStages()->whereNotNull('invoice_id')->exists();
    }

    public function isFullyStageInvoiced(): bool
    {
        return $this->is_staged
            && $this->paymentPlanStages()->exists()
            && $this->paymentPlanStages()->whereNull('invoice_id')->doesntExist();
    }

    public function recalculateTotals(): void
    {
        $items = $this->items;

        $this->subtotal = $items->sum(fn ($item) => $item->quantity * $item->unit_price);
        $this->vat_total = $items->sum('vat_amount');
        $this->discount_total = $this->computeDiscountTotal($this->subtotal);
        $this->total = $this->subtotal - $this->discount_total + $this->vat_total;

        $this->save();
    }

    public function isExpired(): bool
    {
        return $this->expiry_date && $this->expiry_date->isPast() && ! in_array($this->status, ['converted', 'accepted']);
    }

    public function balanceDue(): float
    {
        return round((float) $this->total - (float) $this->advance_received, 2);
    }

    /**
     * Mirrors Invoice::pdfData()'s "Paid" + Money::balanceRow() extra_rows
     * pair — same visual spot (right under the Total line, in every print
     * layout) so a proforma's advance/balance reads exactly like an
     * invoice's does, instead of being buried in free-text notes.
     */
    public function paymentExtraRows(): array
    {
        if (! $this->advance_received || $this->advance_received <= 0) {
            return [];
        }

        return array_values(array_filter([
            ['label' => __('Paid'), 'label_ar' => 'المدفوع', 'value' => $this->advance_received, 'emphasis' => true, 'variant' => 'green'],
            \App\Support\Money::balanceRow($this->balanceDue()),
        ]));
    }

    /**
     * The view data documents.print.pdf needs to render this quotation —
     * moved here (from a private QuotationController method) so anything
     * that needs this quotation's own rendered PDF, such as
     * CompanyLetterController merging a referenced quotation into a
     * letter's combined download, can build it without reaching into the
     * controller. Mirrors Invoice::pdfData()'s existing pattern.
     */
    public function pdfData(): array
    {
        $this->loadMissing('items', 'client', 'bankAccount');

        $bankAccount = $this->bankAccount ?? $this->company->defaultBankAccount();

        $doc = [
            'type_label' => $this->type === 'proforma' ? __('Proforma Invoice') : __('Quotation'),
            'type_label_ar' => $this->type === 'proforma' ? 'فاتورة أولية' : 'عرض سعر',
            'number' => $this->quotation_number,
            'date_label' => __('Issued'),
            'date' => $this->issue_date,
            'date2_label' => __('Valid until'),
            'date2_label_ar' => 'صالح حتى',
            'date2' => $this->expiry_date,
            'party_label' => __('To'),
            'party_label_ar' => 'العميل',
            'party' => $this->client,
            'lines' => $this->items,
            'subtotal' => $this->subtotal,
            'discount_total' => $this->discount_total,
            'discount_percent' => $this->discount_type === 'percentage' ? $this->discount_value : null,
            'vat_total' => $this->vat_total,
            'total' => $this->total,
            'extra_rows' => $this->paymentExtraRows(),
            'bank_account' => $bankAccount,
            'salesperson' => $this->salesperson,
            'notes' => $this->notes,
        ];

        return [
            'doc' => $doc,
            'company' => $this->company,
            'template' => $this->company->defaultTemplateFor($this->type),
        ];
    }
}
