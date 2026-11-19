<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    public const SYMBOL_POSITIONS = ['before', 'after'];

    protected $fillable = [
        'code', 'name', 'symbol', 'decimal_places', 'decimal_separator',
        'thousands_separator', 'symbol_position', 'is_active', 'is_default', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'decimal_places' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public static function default(): ?self
    {
        return static::query()->where('is_default', true)->first()
            ?? static::query()->orderBy('sort_order')->first();
    }

    /**
     * Exactly one currency is ever the default — making $this the default
     * atomically unsets every other row's flag first.
     */
    public function makeDefault(): void
    {
        static::query()->where('id', '!=', $this->id)->update(['is_default' => false]);
        $this->forceFill(['is_default' => true, 'is_active' => true])->save();
    }
}
