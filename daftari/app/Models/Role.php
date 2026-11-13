<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Support\Permissions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'name', 'slug', 'is_system', 'permissions'];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'permissions' => 'array',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function hasPermission(string $key): bool
    {
        return in_array($key, $this->permissions ?? [], true);
    }

    public static function seedSystemRoles(int $companyId): void
    {
        $labels = Permissions::systemRoleLabels();

        foreach (Permissions::systemRolePresets() as $slug => $permissions) {
            static::firstOrCreate(
                ['company_id' => $companyId, 'slug' => $slug],
                ['name' => $labels[$slug], 'is_system' => true, 'permissions' => $permissions]
            );
        }
    }
}
