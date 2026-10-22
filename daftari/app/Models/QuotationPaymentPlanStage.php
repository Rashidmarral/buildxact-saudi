<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One installment of a staged quotation's payment plan (see
 * Quotation::is_staged) — a percentage share of the quotation's
 * VAT-inclusive total ("20% advance", "30% on completion of X"), invoiced
 * independently whenever the company is ready to bill it, rather than all
 * at once like a normal quotation-to-invoice conversion.
 */
class QuotationPaymentPlanStage extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'quotation_id', 'sort_order', 'description', 'percentage',
        'invoice_id', 'invoiced_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'percentage' => 'decimal:2',
            'invoiced_at' => 'datetime',
        ];
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** This stage's share of the quotation's VAT-inclusive total, in SAR. */
    public function amount(): float
    {
        return round((float) $this->quotation->total * ((float) $this->percentage / 100), 2);
    }
}
