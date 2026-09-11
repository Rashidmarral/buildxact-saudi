<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'code', 'name', 'name_ar', 'status', 'client_id',
        'start_date', 'end_date', 'target_revenue', 'cost_ceiling', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /**
     * Billed revenue: totals of every non-draft invoice linked to this
     * project — real numbers from real invoices, not a stored estimate.
     */
    public function revenue(): float
    {
        return (float) $this->invoices()->whereNotIn('status', ['draft', 'cancelled'])->sum('total');
    }

    public function costs(): float
    {
        return (float) $this->expenses()->sum('amount');
    }

    public function margin(): float
    {
        return $this->revenue() - $this->costs();
    }

    public function marginPercent(): ?float
    {
        $revenue = $this->revenue();

        return $revenue > 0 ? round(($this->margin() / $revenue) * 100, 1) : null;
    }
}
