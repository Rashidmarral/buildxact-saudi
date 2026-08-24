<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'plan_id', 'status', 'billing_cycle',
        'current_period_start', 'current_period_end', 'cancelled_at',
        'expiry_reminder_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'cancelled_at' => 'datetime',
            'expiry_reminder_sent_at' => 'datetime',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function isTrial(): bool
    {
        return $this->status === 'trialing';
    }
}
