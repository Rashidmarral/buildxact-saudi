<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VatReturnPeriod extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'created_by', 'period_start', 'period_end',
        'output_tax', 'input_tax_purchases', 'expense_tax', 'net_recoverable_input_tax',
        'credit_brought_forward', 'amount_payable', 'credit_carried_forward', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'output_tax' => 'decimal:2',
            'input_tax_purchases' => 'decimal:2',
            'expense_tax' => 'decimal:2',
            'net_recoverable_input_tax' => 'decimal:2',
            'credit_brought_forward' => 'decimal:2',
            'amount_payable' => 'decimal:2',
            'credit_carried_forward' => 'decimal:2',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The most recently filed period for this company that ends before
     * the given date — its credit_carried_forward is what the next
     * period's credit_brought_forward should default to, so the chain
     * never requires manually re-typing a figure that's already on file.
     */
    public static function latestBefore(int $companyId, $beforeDate): ?self
    {
        return static::where('company_id', $companyId)
            ->where('period_end', '<', $beforeDate)
            ->orderByDesc('period_end')
            ->first();
    }
}
