<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\Rule;

/**
 * Deliberately does NOT use BelongsToCompany — company_id is nullable here
 * (NULL means Daftari's own platform-level row for subscription billing),
 * and every query needs to explicitly choose "platform" vs "this company"
 * rather than have that decided implicitly by a global scope.
 */
class PaymentGateway extends Model
{
    public const PROVIDERS = ['moyasar', 'hyperpay', 'tap', 'paytabs'];

    protected $fillable = [
        'company_id', 'provider', 'mode', 'is_enabled', 'credentials',
    ];

    protected $hidden = ['credentials'];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'credentials' => 'encrypted:array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    public function isPlatformGateway(): bool
    {
        return is_null($this->company_id);
    }

    public static function platform(string $provider): ?self
    {
        return static::whereNull('company_id')->where('provider', $provider)->first();
    }

    public static function forCompany(int $companyId, string $provider): ?self
    {
        return static::where('company_id', $companyId)->where('provider', $provider)->first();
    }

    /**
     * Credential fields each provider needs, shared by the admin
     * (platform-level) and company (per-tenant) settings controllers so
     * the two never drift out of sync.
     */
    public static function credentialRulesFor(string $provider): array
    {
        return match ($provider) {
            'moyasar' => [
                'secret_key' => ['required', 'string', 'max:255'],
                'webhook_secret' => ['nullable', 'string', 'max:255'],
            ],
            'hyperpay' => [
                'access_token' => ['required', 'string', 'max:500'],
                'entity_id' => ['required', 'string', 'max:255'],
            ],
            'tap' => [
                'secret_key' => ['required', 'string', 'max:255'],
            ],
            'paytabs' => [
                'profile_id' => ['required', 'string', 'max:255'],
                'server_key' => ['required', 'string', 'max:255'],
                'region' => ['required', Rule::in(['sa', 'com'])],
            ],
            default => [],
        };
    }
}
