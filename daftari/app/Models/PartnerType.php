<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An admin-defined partner category and its commission rule (item 12 of
 * the Sales, Compliance & Business Growth request: "configurable partner
 * types & commission rules"). Every field here — including whether a
 * type exists at all — is set by the operator from Admin -> Partner
 * Program; nothing is seeded or hard-coded.
 */
class PartnerType extends Model
{
    public const COMMISSION_TYPES = ['percentage', 'fixed'];

    protected $fillable = [
        'name_en', 'name_ar', 'slug', 'commission_type', 'commission_value', 'is_recurring', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'commission_value' => 'decimal:2',
            'is_recurring' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function partners(): HasMany
    {
        return $this->hasMany(Partner::class);
    }

    public function name(): string
    {
        $locale = app()->getLocale();

        return ($locale === 'ar' ? $this->name_ar : null) ?: $this->name_en;
    }

    public function commissionLabel(): string
    {
        $value = $this->commission_type === 'percentage'
            ? rtrim(rtrim(number_format((float) $this->commission_value, 2), '0'), '.').'%'
            : \App\Support\Money::format((float) $this->commission_value);

        return $this->is_recurring
            ? __(':value per renewal', ['value' => $value])
            : __(':value one-time', ['value' => $value]);
    }
}
