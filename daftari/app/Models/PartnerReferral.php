<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerReferral extends Model
{
    public const STATUSES = ['pending', 'qualified', 'converted', 'rejected'];

    public const COMMISSION_STATUSES = ['unearned', 'approved', 'paid'];

    protected $fillable = [
        'partner_id', 'lead_id', 'company_id', 'status', 'commission_amount', 'commission_status', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'commission_amount' => 'decimal:2',
        ];
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'pending' => __('Pending'),
            'qualified' => __('Qualified'),
            'converted' => __('Converted'),
            'rejected' => __('Rejected'),
            default => ucfirst($this->status),
        };
    }

    public function statusBadgeClasses(): string
    {
        return match ($this->status) {
            'pending' => 'bg-slate-100 text-slate-600',
            'qualified' => 'bg-sky-50 text-sky-700',
            'converted' => 'bg-emerald-50 text-emerald-700',
            'rejected' => 'bg-red-50 text-red-700',
            default => 'bg-slate-100 text-slate-600',
        };
    }

    public function commissionStatusLabel(): string
    {
        return match ($this->commission_status) {
            'unearned' => __('Unearned'),
            'approved' => __('Approved — unpaid'),
            'paid' => __('Paid'),
            default => ucfirst($this->commission_status),
        };
    }

    public function commissionStatusBadgeClasses(): string
    {
        return match ($this->commission_status) {
            'unearned' => 'bg-slate-100 text-slate-500',
            'approved' => 'bg-amber-50 text-amber-700',
            'paid' => 'bg-emerald-50 text-emerald-700',
            default => 'bg-slate-100 text-slate-500',
        };
    }
}
