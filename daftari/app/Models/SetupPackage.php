<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SetupPackage extends Model
{
    protected $fillable = [
        'name_en', 'name_ar', 'slug', 'description_en', 'description_ar', 'price', 'features_en', 'features_ar', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'features_en' => 'array',
            'features_ar' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function requests(): HasMany
    {
        return $this->hasMany(SetupPackageRequest::class);
    }

    public function name(): string
    {
        $locale = app()->getLocale();

        return ($locale === 'ar' ? $this->name_ar : null) ?: $this->name_en;
    }

    public function description(): ?string
    {
        $locale = app()->getLocale();

        return ($locale === 'ar' ? $this->description_ar : null) ?: $this->description_en;
    }

    public function features(): array
    {
        $locale = app()->getLocale();

        return ($locale === 'ar' ? $this->features_ar : null) ?: ($this->features_en ?? []);
    }

    public function priceLabel(): string
    {
        return $this->price !== null ? \App\Support\Money::format((float) $this->price) : __('Contact us for pricing');
    }
}
