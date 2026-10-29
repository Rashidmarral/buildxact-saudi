<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubcontractReportCategory extends Model
{
    public const ITEMIZED = 'itemized';

    public const MEASURED = 'measured';

    protected $fillable = [
        'subcontract_id', 'sort_order', 'label', 'display_mode',
        'match_terms', 'quantity', 'unit', 'rate', 'amount',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'rate' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }

    public function subcontract(): BelongsTo
    {
        return $this->belongsTo(Subcontract::class);
    }

    public function isMeasured(): bool
    {
        return $this->display_mode === self::MEASURED;
    }

    /**
     * @return array<int, string>
     */
    public function keywords(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $this->match_terms))));
    }
}
