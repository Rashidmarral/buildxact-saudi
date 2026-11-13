<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A formal ZATCA "prepayment tax invoice" (InvoiceTypeCode 386) — issued
 * for an advance/deposit collected before the main supply, since VAT is
 * due on that advance at the moment it's received, not deferred to
 * whenever the final invoice follows. See the create_prepayment_invoices_table
 * migration for why this has no line items of its own, and
 * ZatcaXmlGenerator::generateForPrepayment()/generate() for how a later
 * Invoice references this document and reports it in cbc:PrepaidAmount.
 */
class PrepaymentInvoice extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'client_id', 'project_id', 'created_by', 'tax_rate_id',
        'prepayment_number', 'type', 'status', 'issue_date', 'description',
        'amount', 'vat_rate', 'vat_amount', 'total', 'currency', 'notes', 'qr_code',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }

    /**
     * The one final invoice this advance has been applied to, if any —
     * see Invoice::appliesPrepaymentInvoice(). Null means it's still
     * available to be linked to a future invoice for the same client.
     */
    public function appliedToInvoice(): HasOne
    {
        return $this->hasOne(Invoice::class, 'applies_prepayment_invoice_id');
    }

    public function zatcaInvoiceLogs(): HasMany
    {
        return $this->hasMany(ZatcaInvoiceLog::class, 'prepayment_invoice_id');
    }

    /**
     * Partial/multiple draw-downs of this advance against several future
     * invoices — see the create_prepayment_invoice_applications_table
     * migration. Coexists with appliedToInvoice() (the simpler, older,
     * single-invoice-full-amount path): a prepayment is settled through
     * exactly one of the two, never both.
     */
    public function applications(): HasMany
    {
        return $this->hasMany(PrepaymentInvoiceApplication::class);
    }

    public function appliedTotal(): float
    {
        if ($this->appliedToInvoice()->exists()) {
            return (float) $this->total;
        }

        return (float) $this->applications()->sum('total');
    }

    public function remainingTotal(): float
    {
        return round((float) $this->total - $this->appliedTotal(), 2);
    }

    public function isZatcaSynced(): bool
    {
        return $this->zatcaInvoiceLogs()->whereIn('status', ['cleared', 'reported'])->exists();
    }

    public function isApplied(): bool
    {
        return $this->appliedToInvoice()->exists() || $this->applications()->exists();
    }
}
