<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasCustomFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Item extends Model
{
    use BelongsToCompany, HasCustomFields;

    public const CUSTOM_FIELD_ENTITY_TYPE = 'item';

    public const TRACKING_TYPES = ['none', 'lot', 'serial'];

    public const UNIT_CODES = [
        'PCE' => 'Piece',
        'KGM' => 'Kilogram',
        'GRM' => 'Gram',
        'LTR' => 'Litre',
        'MTR' => 'Metre',
        'MTK' => 'Square metre',
        'MTQ' => 'Cubic metre',
        'BX' => 'Box',
        'CT' => 'Carton',
        'PK' => 'Pack',
        'HUR' => 'Hour',
        'DAY' => 'Day',
        'MON' => 'Month',
        'EA' => 'Each',
    ];

    protected $fillable = [
        'company_id', 'name', 'name_ar', 'description', 'sku', 'barcode', 'category',
        'expiry_date', 'item_type', 'unit', 'unit_code', 'image_path', 'base_unit_id',
        'unit_price', 'purchase_price', 'vat_rate', 'is_active', 'track_inventory', 'reorder_point', 'tracking_type',
        'is_kit', 'parent_item_id', 'variant_label',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'track_inventory' => 'boolean',
            'expiry_date' => 'date',
            'is_kit' => 'boolean',
        ];
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(ItemStock::class);
    }

    public function lots(): HasMany
    {
        return $this->hasMany(ItemLot::class);
    }

    public function isLotOrSerialTracked(): bool
    {
        return in_array($this->tracking_type, ['lot', 'serial'], true);
    }

    /**
     * The items this kit expands into on sale — see
     * User\InvoiceController::applyStock(), which deducts each component's
     * own stock (quantity per one unit of the kit, times the invoice
     * line's quantity) instead of the kit item's own stock, since a kit
     * never carries an ItemStock row of its own.
     */
    public function kitComponents(): HasMany
    {
        return $this->hasMany(ItemKitComponent::class, 'kit_item_id');
    }

    public function parentItem(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'parent_item_id');
    }

    /**
     * Other items sharing this item's parent (or, called on a parent
     * itself, its own variants) — e.g. "Red / Large" and "Blue / Small"
     * of the same base product, each its own full Item with its own SKU,
     * barcode, price and stock rather than a size/color pair stored on a
     * single row.
     */
    public function variants(): HasMany
    {
        return $this->hasMany(Item::class, 'parent_item_id');
    }

    public function isVariant(): bool
    {
        return $this->parent_item_id !== null;
    }

    public function totalStock(): float
    {
        return (float) $this->stocks()->sum('quantity');
    }

    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'base_unit_id');
    }

    public function itemUnits(): HasMany
    {
        return $this->hasMany(ItemUnit::class);
    }

    /**
     * How many of the item's base unit one unit of $unitId equals, e.g.
     * base unit "Ton", alt unit "Bag" with conversion_factor 40 means
     * factorFor(bagId) === 40.0 (1 Bag = 1/40 Ton). Null or the base unit
     * itself always returns 1 — no conversion needed.
     */
    public function conversionFactorFor(?int $unitId): float
    {
        if (! $unitId || $unitId === $this->base_unit_id) {
            return 1.0;
        }

        $itemUnit = $this->relationLoaded('itemUnits')
            ? $this->itemUnits->firstWhere('unit_id', $unitId)
            : $this->itemUnits()->where('unit_id', $unitId)->first();

        return $itemUnit ? (float) $itemUnit->conversion_factor : 1.0;
    }

    /**
     * The per-unit sale/purchase price for $unitId: the item unit's own
     * price override if one is set, otherwise derived from the base
     * unit_price divided by the conversion factor.
     */
    public function priceForUnit(?int $unitId): float
    {
        if (! $unitId || $unitId === $this->base_unit_id) {
            return (float) $this->unit_price;
        }

        $itemUnit = $this->relationLoaded('itemUnits')
            ? $this->itemUnits->firstWhere('unit_id', $unitId)
            : $this->itemUnits()->where('unit_id', $unitId)->first();

        if ($itemUnit && $itemUnit->unit_price !== null) {
            return (float) $itemUnit->unit_price;
        }

        $factor = $itemUnit ? (float) $itemUnit->conversion_factor : 1.0;

        return $factor > 0 ? (float) $this->unit_price / $factor : (float) $this->unit_price;
    }

    /**
     * Converts a line quantity sold/bought in $unitId into the item's base
     * unit quantity, for stock tracking (which is always kept in the base
     * unit regardless of what unit a given line was transacted in).
     */
    public function baseQuantityFor(float $quantity, ?int $unitId): float
    {
        $factor = $this->conversionFactorFor($unitId);

        return $factor > 0 ? $quantity / $factor : $quantity;
    }
}
