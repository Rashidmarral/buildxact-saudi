<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosShift extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'register_id', 'opened_by', 'opened_at', 'opening_cash', 'status',
        'closed_by', 'closed_at', 'counted_cash', 'expected_cash', 'cash_difference', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'opening_cash' => 'decimal:2',
            'counted_cash' => 'decimal:2',
            'expected_cash' => 'decimal:2',
            'cash_difference' => 'decimal:2',
        ];
    }

    public function register(): BelongsTo
    {
        return $this->belongsTo(PosRegister::class, 'register_id');
    }

    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(PosSale::class, 'shift_id');
    }

    public function cashSalesTotal(): float
    {
        return (float) $this->sales()
            ->where('status', 'completed')
            ->join('pos_payments', 'pos_payments.pos_sale_id', '=', 'pos_sales.id')
            ->where('pos_payments.method', 'cash')
            ->sum('pos_payments.amount');
    }
}
