<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CollectionNote extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'client_id', 'note', 'promise_to_pay_date', 'promise_to_pay_amount', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'promise_to_pay_date' => 'date',
            'promise_to_pay_amount' => 'decimal:2',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
