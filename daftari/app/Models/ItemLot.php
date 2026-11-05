<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A received quantity of an item tracked by lot/batch (lot_number,
 * multiple units) or serial (serial_number, always quantity 1) — see
 * Item::tracking_type. Every ItemLot's quantity is a slice of the same
 * item+warehouse total ItemStock already tracks; receiving or consuming
 * a lot keeps both in sync (see ItemLotController) rather than a lot
 * being a second, disconnected source of truth for stock on hand.
 */
class ItemLot extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'item_id', 'warehouse_id', 'lot_number', 'serial_number',
        'expiry_date', 'quantity', 'received_by', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'expiry_date' => 'date',
            'quantity' => 'decimal:2',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function isExpired(): bool
    {
        return $this->expiry_date && $this->expiry_date->isPast();
    }

    public function isExpiringSoon(int $withinDays = 30): bool
    {
        return $this->expiry_date && ! $this->isExpired() && now()->diffInDays($this->expiry_date, false) <= $withinDays;
    }
}
