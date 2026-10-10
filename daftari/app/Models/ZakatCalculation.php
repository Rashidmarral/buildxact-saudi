<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZakatCalculation extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'created_by', 'period_end_date', 'rate_type',
        'equity_amount', 'long_term_liabilities', 'net_fixed_assets',
        'other_deductions', 'zakat_base', 'zakat_due', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'period_end_date' => 'date',
            'equity_amount' => 'decimal:2',
            'long_term_liabilities' => 'decimal:2',
            'net_fixed_assets' => 'decimal:2',
            'other_deductions' => 'decimal:2',
            'zakat_base' => 'decimal:2',
            'zakat_due' => 'decimal:2',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function rate(string $rateType): float
    {
        return $rateType === 'gregorian' ? 0.025775 : 0.025;
    }
}
