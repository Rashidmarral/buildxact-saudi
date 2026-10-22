<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Support\FeatureRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A company's request to install a paid module (see FeatureRegistry's
 * 'gated' entries — Payroll, POS, Restaurant Management, ...). Approving
 * one (Admin > Module requests) sets the same CompanyOverride the
 * Companies screen's feature-override list already uses, so this is
 * purely a front door onto that existing mechanism, not a second source
 * of truth for entitlement.
 */
class ModuleRequest extends Model
{
    use BelongsToCompany;

    public const STATUSES = ['requested', 'approved', 'rejected'];

    protected $fillable = [
        'company_id', 'module_key', 'status', 'note', 'admin_note', 'requested_by', 'reviewed_by', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function moduleLabel(): string
    {
        return FeatureRegistry::catalog()[$this->module_key]['label'] ?? $this->module_key;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'requested' => __('Pending review'),
            'approved' => __('Approved'),
            'rejected' => __('Rejected'),
            default => ucfirst($this->status),
        };
    }

    public function statusBadgeClasses(): string
    {
        return match ($this->status) {
            'requested' => 'bg-amber-50 text-amber-700',
            'approved' => 'bg-emerald-50 text-emerald-700',
            'rejected' => 'bg-red-50 text-red-700',
            default => 'bg-slate-100 text-slate-500',
        };
    }
}
