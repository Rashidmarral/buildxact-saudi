<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FxRevaluationLine extends Model
{
    protected $fillable = [
        'fx_revaluation_id', 'document_type', 'document_id', 'document_number', 'party_name',
        'currency', 'foreign_balance', 'booked_rate', 'revaluation_rate',
        'booked_base_amount', 'revalued_base_amount', 'unrealized_gain_loss',
    ];

    protected function casts(): array
    {
        return [
            'foreign_balance' => 'float',
            'booked_rate' => 'float',
            'revaluation_rate' => 'float',
            'booked_base_amount' => 'float',
            'revalued_base_amount' => 'float',
            'unrealized_gain_loss' => 'float',
        ];
    }

    public function fxRevaluation(): BelongsTo
    {
        return $this->belongsTo(FxRevaluation::class);
    }
}
