<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * The Machinery & Equipment module's asset registry. Cost, depreciation,
 * and disposal accounting live on the linked FixedAsset (see
 * FixedAssetLifecycleService) — this model only carries the
 * machinery-specific facts and the rental/deployment/revenue/expense
 * relations that make up a machine's complete picture.
 */
class MachineryAsset extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'fixed_asset_id', 'asset_code', 'name', 'name_ar', 'category',
        'make', 'model', 'serial_number', 'plate_or_chassis_number', 'year_of_manufacture',
        'status', 'default_rental_rate', 'rental_rate_type', 'operator_employee_id',
        'registration_expiry_date', 'insurance_expiry_date',
        'registration_reminder_sent_at', 'insurance_reminder_sent_at', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'registration_expiry_date' => 'date',
            'insurance_expiry_date' => 'date',
            'registration_reminder_sent_at' => 'datetime',
            'insurance_reminder_sent_at' => 'datetime',
            'default_rental_rate' => 'decimal:2',
        ];
    }

    public function fixedAsset(): BelongsTo
    {
        return $this->belongsTo(FixedAsset::class);
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'operator_employee_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function rentalContracts(): HasMany
    {
        return $this->hasMany(MachineryRentalContract::class);
    }

    public function deployments(): HasMany
    {
        return $this->hasMany(MachineryProjectDeployment::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function letters(): HasMany
    {
        return $this->hasMany(CompanyLetter::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /**
     * Every non-draft/cancelled invoice tagged to this machine — rental
     * income and, once sold, the sale invoice if one was raised.
     */
    public function totalRevenue(): float
    {
        return (float) $this->invoices()->whereNotIn('status', ['draft', 'cancelled'])->sum('total');
    }

    /**
     * Every approved expense tagged to this machine (fuel, maintenance,
     * operator wages) — mirrors Project::cashPaid()'s own 'approved'
     * filter, the same real-cash-posted condition.
     */
    public function totalRunningCost(): float
    {
        return (float) $this->expenses()->where('status', 'approved')->sum('gross_amount');
    }

    /**
     * A simple, clearly-labeled estimate, not a GL figure: revenue minus
     * running cost minus depreciation accrued since acquisition. The real,
     * authoritative depreciation ledger is the linked FixedAsset's own
     * accumulated_depreciation (posted monthly by AssetDepreciationService).
     */
    public function netResult(): float
    {
        return $this->totalRevenue() - $this->totalRunningCost() - (float) ($this->fixedAsset?->accumulated_depreciation ?? 0);
    }

    /**
     * Share of the machine's owned lifetime spent earning (rented out or
     * deployed on a project), 0-100. A machine's status only allows one
     * rental/deployment at a time, so these periods never overlap and
     * summing their days can't double-count. Null before there's an
     * acquisition date to measure from.
     */
    public function utilizationPercent(): ?float
    {
        $acquiredAt = $this->fixedAsset?->acquisition_date ?? $this->created_at;

        if (! $acquiredAt) {
            return null;
        }

        // startOfDay() on both sides — acquiredAt may be a plain date (no
        // time component) while now() carries the current time-of-day;
        // diffing a date against a full timestamp gives a fractional day
        // count that drifts with the clock, unlike activeDays() above
        // (start_date/end_date are both dates, so that diff is always a
        // clean whole number).
        $ownedDays = max(1, $acquiredAt->copy()->startOfDay()->diffInDays(now()->startOfDay()) + 1);
        $activeDays = $this->rentalContracts->sum(fn (MachineryRentalContract $c) => $c->activeDays())
            + $this->deployments->sum(fn (MachineryProjectDeployment $d) => $d->activeDays());

        return round(min(100, ($activeDays / $ownedDays) * 100), 1);
    }
}
