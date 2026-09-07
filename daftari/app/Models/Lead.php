<?php

namespace App\Models;

use App\Mail\NewLeadMail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Mail;

/**
 * A prospective customer for this SaaS itself (not a tenant's own
 * customer — see Client for that). Tracks the sales pipeline the operator
 * asked for: Lead -> Contacted -> Qualified -> Demo -> Trial -> Setup ->
 * Paid -> Active -> Renewal, with Lost reachable from any stage. Created
 * from public lead-capture forms (the /contact page and /get-started
 * industry landing pages) or manually by an admin.
 */
class Lead extends Model
{
    public const STAGES = [
        'lead', 'contacted', 'qualified', 'demo', 'trial', 'setup', 'paid', 'active', 'renewal', 'lost',
    ];

    /**
     * Stages that count as a won/paying relationship, for the conversion
     * stats on the admin index page.
     */
    public const WON_STAGES = ['paid', 'active', 'renewal'];

    public const SOURCES = ['contact_form', 'get_started', 'zatca_readiness', 'referral', 'manual', 'other'];

    public const INDUSTRIES = ['contracting', 'trading', 'retail', 'restaurants', 'auto_workshops', 'services', 'other'];

    protected $fillable = [
        'name', 'email', 'phone', 'company_name', 'industry', 'source', 'message', 'status',
        'assigned_admin_id', 'converted_company_id', 'demo_at', 'next_follow_up_at', 'lost_reason', 'last_activity_at',
    ];

    protected function casts(): array
    {
        return [
            'demo_at' => 'datetime',
            'next_follow_up_at' => 'datetime',
            'last_activity_at' => 'datetime',
        ];
    }

    /**
     * Single entry point for every public lead-capture form (the /contact
     * page and /get-started, itself reused by every industry landing
     * page's call-to-action) so creation never drifts from the rest of
     * the pipeline. $attributes must already contain name/email and any
     * of phone/company_name/industry/message. $notify is false for the
     * /contact page, which already emails support_email a full copy of
     * the message via ContactFormMail — this just adds the CRM record
     * without a second, duplicate notification email.
     */
    public static function capture(array $attributes, string $source, bool $notify = true): self
    {
        $lead = self::create(array_merge($attributes, [
            'source' => in_array($source, self::SOURCES, true) ? $source : 'other',
            'status' => 'lead',
            'last_activity_at' => now(),
        ]));

        $notifyEmail = Setting::get('support_email', config('mail.from.address'));
        if ($notify && $notifyEmail) {
            Mail::to($notifyEmail)->send(new NewLeadMail($lead));
        }

        return $lead;
    }

    public function assignedAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_admin_id');
    }

    public function convertedCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'converted_company_id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(LeadNote::class)->latest('id');
    }

    public function isWon(): bool
    {
        return in_array($this->status, self::WON_STAGES, true);
    }

    public function isLost(): bool
    {
        return $this->status === 'lost';
    }

    public function stageLabel(): string
    {
        return match ($this->status) {
            'lead' => __('Lead'),
            'contacted' => __('Contacted'),
            'qualified' => __('Qualified'),
            'demo' => __('Demo'),
            'trial' => __('Trial'),
            'setup' => __('Setup'),
            'paid' => __('Paid'),
            'active' => __('Active'),
            'renewal' => __('Renewal'),
            'lost' => __('Lost'),
            default => ucfirst($this->status),
        };
    }

    public function stageBadgeClasses(): string
    {
        return match ($this->status) {
            'lead' => 'bg-slate-100 text-slate-600',
            'contacted' => 'bg-sky-50 text-sky-700',
            'qualified' => 'bg-indigo-50 text-indigo-700',
            'demo' => 'bg-violet-50 text-violet-700',
            'trial' => 'bg-amber-50 text-amber-700',
            'setup' => 'bg-amber-50 text-amber-700',
            'paid' => 'bg-emerald-50 text-emerald-700',
            'active' => 'bg-emerald-50 text-emerald-700',
            'renewal' => 'bg-teal-50 text-teal-700',
            'lost' => 'bg-red-50 text-red-700',
            default => 'bg-slate-100 text-slate-600',
        };
    }

    public function sourceLabel(): string
    {
        return match ($this->source) {
            'contact_form' => __('Contact form'),
            'get_started' => __('Get started page'),
            'zatca_readiness' => __('ZATCA readiness check'),
            'referral' => __('Referral'),
            'manual' => __('Added manually'),
            default => __('Other'),
        };
    }

    public function industryLabel(): ?string
    {
        return match ($this->industry) {
            'contracting' => __('Contracting & Construction'),
            'trading' => __('Trading'),
            'retail' => __('Retail'),
            'restaurants' => __('Restaurants'),
            'auto_workshops' => __('Auto Workshops'),
            'services' => __('Services'),
            'other' => __('Other'),
            default => null,
        };
    }
}
