<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class TaxRate extends Model
{
    use BelongsToCompany;

    public const TYPE_STANDARD = 'standard';

    public const TYPE_ZERO_RATED = 'zero_rated';

    public const TYPE_EXEMPT = 'exempt';

    public const TYPES = [
        self::TYPE_STANDARD => 'Standard',
        self::TYPE_ZERO_RATED => 'Zero-rated',
        self::TYPE_EXEMPT => 'Exempt',
    ];

    protected $fillable = [
        'company_id', 'name', 'rate', 'type', 'is_active', 'is_default', 'effective_date',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:2',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'effective_date' => 'date',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Only rates already in force as of $date (or with no effective_date
     * at all, meaning "always in force") — lets a company keep old rates
     * on record for historical documents without offering them for new
     * ones once a newer rate has taken over.
     */
    public function scopeEffectiveAsOf(Builder $query, \DateTimeInterface|string|null $date = null): Builder
    {
        $date = $date ? Carbon::parse($date) : now();

        return $query->where(function ($q) use ($date) {
            $q->whereNull('effective_date')->orWhere('effective_date', '<=', $date->toDateString());
        });
    }

    /**
     * The rate that pre-fills a new document line for $companyId — the
     * company's own explicit default if it set one, else its active
     * standard-type rate, else a bare 0 (never a hardcoded 15).
     */
    public static function defaultRate(int $companyId): float
    {
        $rate = static::query()
            ->where('company_id', $companyId)
            ->active()
            ->effectiveAsOf()
            ->where(function ($q) {
                $q->where('is_default', true)->orWhere('type', self::TYPE_STANDARD);
            })
            ->orderByDesc('is_default')
            ->first();

        return $rate ? (float) $rate->rate : 0.0;
    }

    /**
     * Exactly one tax rate is ever the default for a company.
     */
    public function makeDefault(): void
    {
        static::query()->where('company_id', $this->company_id)->where('id', '!=', $this->id)->update(['is_default' => false]);
        $this->forceFill(['is_default' => true, 'is_active' => true])->save();
    }

    /**
     * Seeds the standard Saudi VAT set for a brand-new company — Standard
     * 15% (the default), Zero-rated 0%, and Exempt — so the tax rate list
     * is never empty at signup. Idempotent: safe to call again (e.g. from
     * a backfill command) without creating duplicates.
     */
    public static function seedDefaults(int $companyId): void
    {
        $defaults = [
            ['name' => 'VAT 15%', 'rate' => 15.00, 'type' => self::TYPE_STANDARD, 'is_default' => true],
            ['name' => 'VAT 0%', 'rate' => 0.00, 'type' => self::TYPE_ZERO_RATED, 'is_default' => false],
            ['name' => 'Exempt', 'rate' => 0.00, 'type' => self::TYPE_EXEMPT, 'is_default' => false],
        ];

        foreach ($defaults as $rate) {
            static::query()->firstOrCreate(
                ['company_id' => $companyId, 'name' => $rate['name']],
                $rate + ['company_id' => $companyId, 'is_active' => true]
            );
        }
    }
}
