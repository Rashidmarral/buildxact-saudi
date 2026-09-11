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

    // Offline manual payment — never goes through PaymentGatewayDriver /
    // PaymentCheckoutService at all. Listed separately from PROVIDERS
    // (which are the real online-checkout drivers PaymentGatewayManager
    // knows how to resolve) so a bank_transfer row can never accidentally
    // be routed through the online-checkout pipeline.
    public const BANK_TRANSFER = 'bank_transfer';

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
     *
     * bank_transfer only takes real fields at the platform level — that's
     * Daftari's own bank account, which subscription payers wire money to.
     * At company level it's just an on/off switch: companies already
     * manage their own bank accounts under Settings > Cash & Banks, and
     * the public invoice page already shows whichever one is attached to
     * the invoice (or the company's default) — no need to duplicate that
     * here.
     */
    public static function credentialRulesFor(string $provider, bool $isPlatform = true): array
    {
        if ($provider === self::BANK_TRANSFER) {
            return $isPlatform ? [
                'bank_name' => ['required', 'string', 'max:255'],
                'account_holder_name' => ['required', 'string', 'max:255'],
                'iban' => ['required', 'string', 'max:34'],
                'account_number' => ['nullable', 'string', 'max:50'],
                'swift_code' => ['nullable', 'string', 'max:20'],
            ] : [];
        }

        // Secret fields (see secretKeysFor()) are 'nullable' here even
        // though they're conceptually required — the edit form never
        // redisplays a stored secret in plaintext, so leaving one blank on
        // save means "keep the existing value", not "clear it". The
        // controller enforces that a secret must exist (new or already
        // stored) after merging before it will save.
        return match ($provider) {
            'moyasar' => [
                'secret_key' => ['nullable', 'string', 'max:255'],
                'webhook_secret' => ['nullable', 'string', 'max:255'],
            ],
            'hyperpay' => [
                'access_token' => ['nullable', 'string', 'max:500'],
                'entity_id' => ['required', 'string', 'max:255'],
            ],
            'tap' => [
                'secret_key' => ['nullable', 'string', 'max:255'],
            ],
            'paytabs' => [
                'profile_id' => ['required', 'string', 'max:255'],
                'server_key' => ['nullable', 'string', 'max:255'],
                'region' => ['required', Rule::in(['sa', 'com'])],
            ],
            default => [],
        };
    }

    /**
     * Credential keys that hold a secret value — masked (not redisplayed)
     * in the edit form, and preserved rather than overwritten when the
     * field is submitted blank. See credentialRulesFor().
     */
    public static function secretKeysFor(string $provider): array
    {
        return match ($provider) {
            'moyasar' => ['secret_key', 'webhook_secret'],
            'hyperpay' => ['access_token'],
            'tap' => ['secret_key'],
            'paytabs' => ['server_key'],
            default => [],
        };
    }

    /**
     * Of those secret keys, the ones that must resolve to a non-empty
     * value (new submission or already-stored) before the gateway can be
     * saved — webhook_secret, for example, is an optional secret.
     */
    public static function requiredSecretKeysFor(string $provider): array
    {
        return match ($provider) {
            'moyasar' => ['secret_key'],
            'hyperpay' => ['access_token'],
            'tap' => ['secret_key'],
            'paytabs' => ['server_key'],
            default => [],
        };
    }
}
