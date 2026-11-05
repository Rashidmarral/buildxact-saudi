<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerPayout extends Model
{
    public const STATUSES = ['requested', 'processing', 'paid', 'rejected'];

    protected $fillable = [
        'partner_id', 'amount', 'method', 'reference', 'status', 'notes', 'requested_at', 'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'requested_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'requested' => __('Requested'),
            'processing' => __('Processing'),
            'paid' => __('Paid'),
            'rejected' => __('Rejected'),
            default => ucfirst($this->status),
        };
    }

    public function statusBadgeClasses(): string
    {
        return match ($this->status) {
            'requested' => 'bg-amber-50 text-amber-700',
            'processing' => 'bg-sky-50 text-sky-700',
            'paid' => 'bg-emerald-50 text-emerald-700',
            'rejected' => 'bg-red-50 text-red-700',
            default => 'bg-slate-100 text-slate-500',
        };
    }
}
