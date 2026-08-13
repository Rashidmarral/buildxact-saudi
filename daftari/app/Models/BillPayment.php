<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillPayment extends Model
{
    protected $fillable = ['bill_id', 'amount', 'paid_at', 'method', 'reference'];

    protected function casts(): array
    {
        return ['paid_at' => 'date'];
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }
}
