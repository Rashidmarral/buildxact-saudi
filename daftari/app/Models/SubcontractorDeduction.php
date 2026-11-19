<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Credits a subcontractor's own bill for materials the company bought
 * from a third-party supplier and supplied to him — not a Purchase
 * Return (nothing was ever bought from the subcontractor to return to
 * him), and never itself VAT-bearing: the VAT belongs to the real
 * third-party supplier's own bill.
 */
class SubcontractorDeduction extends Model
{
    use BelongsToCompany;

    public const STATUSES = ['issued', 'void'];

    protected $fillable = [
        'company_id', 'bill_id', 'supplier_id', 'branch_id', 'created_by',
        'deduction_number', 'issue_date', 'reason', 'status',
        'amount', 'currency', 'exchange_rate',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
        ];
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
