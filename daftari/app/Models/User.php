<?php

namespace App\Models;

use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmailContract
{
    use HasFactory, MustVerifyEmail, Notifiable;

    // Deliberately does NOT use BelongsToCompany: that trait's global scope
    // calls Auth::user(), and resolving Auth::user() itself queries this
    // model — applying the scope here causes infinite recursion. Team
    // routes scope User queries manually instead (see TeamController).

    protected $fillable = [
        'company_id',
        'name',
        'email',
        'password',
        'role',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
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
