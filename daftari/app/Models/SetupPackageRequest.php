<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SetupPackageRequest extends Model
{
    public const STATUSES = ['requested', 'in_progress', 'completed', 'cancelled'];

    protected $fillable = ['setup_package_id', 'lead_id', 'status', 'completed_at'];

    protected function casts(): array
    {
        return ['completed_at' => 'datetime'];
    }

    public function setupPackage(): BelongsTo
    {
        return $this->belongsTo(SetupPackage::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'requested' => __('Requested'),
            'in_progress' => __('In progress'),
            'completed' => __('Completed'),
            'cancelled' => __('Cancelled'),
            default => ucfirst(str_replace('_', ' ', $this->status)),
        };
    }

    public function statusBadgeClasses(): string
    {
        return match ($this->status) {
            'requested' => 'bg-amber-50 text-amber-700',
            'in_progress' => 'bg-sky-50 text-sky-700',
            'completed' => 'bg-emerald-50 text-emerald-700',
            'cancelled' => 'bg-slate-100 text-slate-500',
            default => 'bg-slate-100 text-slate-500',
        };
    }
}
