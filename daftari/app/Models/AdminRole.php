<?php

namespace App\Models;

use App\Support\AdminPermissions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AdminRole extends Model
{
    protected $fillable = ['name', 'name_ar', 'slug', 'is_system', 'permissions'];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'permissions' => 'array',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'admin_role_user');
    }

    /**
     * View-level check: satisfied by either a full grant (the bare key)
     * or an explicit view-only grant ("$key:view", used by the seeded
     * "Read-only auditor" role — see AdminPermissions::systemRolePresets()).
     * Every route that only reads data (index/show pages) should gate on
     * this, via EnsureAdminPermission's default 'view' level.
     */
    public function hasPermission(string $key): bool
    {
        return in_array($key, $this->permissions ?? [], true)
            || in_array("{$key}:view", $this->permissions ?? [], true);
    }

    /**
     * Manage-level check: only a full grant (the bare key) satisfies
     * this — a "$key:view" entry deliberately never does. Every route
     * that mutates data (create/update/delete/suspend/refund/etc.) must
     * gate on this, via EnsureAdminPermission's 'manage' level. Security
     * audit finding CRIT-04: before this split, hasPermission() alone
     * gated both reads and writes, so the "Read-only auditor" preset —
     * which grants every key — could suspend companies, refund payments,
     * and change plans despite its name.
     */
    public function hasManagePermission(string $key): bool
    {
        return in_array($key, $this->permissions ?? [], true);
    }

    public static function seedSystemRoles(): void
    {
        $labels = AdminPermissions::systemRoleLabels();

        foreach (AdminPermissions::systemRolePresets() as $slug => $permissions) {
            static::firstOrCreate(
                ['slug' => $slug],
                ['name' => $labels[$slug], 'is_system' => true, 'permissions' => $permissions]
            );
        }
    }
}
