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

    public function hasPermission(string $key): bool
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
