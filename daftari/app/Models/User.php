<?php

namespace App\Models;

use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmailContract
{
    use HasApiTokens, HasFactory, MustVerifyEmail, Notifiable;

    // Deliberately does NOT use BelongsToCompany: that trait's global scope
    // calls Auth::user(), and resolving Auth::user() itself queries this
    // model — applying the scope here causes infinite recursion. Team
    // routes scope User queries manually instead (see TeamController).

    protected $fillable = [
        'company_id',
        'name',
        'email',
        'phone',
        'password',
        'role',
        'status',
        // Only ever written server-side (see TeamInviteController::accept())
        // when a signed link already proved control of the address — never
        // sourced from raw, unvalidated request input.
        'email_verified_at',
        // Same rule: only ever set by TwoFactorController after verifying
        // a real TOTP code, never from raw request input.
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function hasTwoFactorEnabled(): bool
    {
        return ! is_null($this->two_factor_confirmed_at);
    }

    public function hasVerifiedPhone(): bool
    {
        return ! is_null($this->phone_verified_at);
    }

    /**
     * Whether this account still needs to complete phone verification
     * before reaching the dashboard — only true when the platform actually
     * requires it (Platform Settings → Signup) and this account hasn't
     * done it yet. Off by default, matching every other opt-in gate here.
     */
    public function needsPhoneVerification(): bool
    {
        return \App\Models\Setting::getBool('signup_require_phone_verification') && ! $this->hasVerifiedPhone();
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isAdminStaff(): bool
    {
        return $this->role === 'admin_staff';
    }

    public function isOwner(): bool
    {
        return $this->role === 'owner';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function hasPermission(string $key): bool
    {
        if ($this->isSuperAdmin() || $this->isOwner()) {
            return true;
        }

        return $this->roles->contains(fn (Role $role) => $role->hasPermission($key));
    }

    public function adminRoles(): BelongsToMany
    {
        return $this->belongsToMany(AdminRole::class, 'admin_role_user');
    }

    /**
     * Platform-level permission check for the admin panel. super_admin
     * always bypasses; admin_staff users need the key granted through one
     * of their assigned AdminRoles. Never true for company users.
     */
    public function hasAdminPermission(string $key): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if (! $this->isAdminStaff()) {
            return false;
        }

        return $this->adminRoles->contains(fn (AdminRole $role) => $role->hasPermission($key));
    }
}
