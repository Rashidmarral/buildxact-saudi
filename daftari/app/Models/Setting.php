<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A flat key/value store for platform-wide config that a super admin needs
 * to change at runtime (trial length, support email, maintenance mode) —
 * distinct from config/daftari.php, which stays as the deploy-time default
 * every key here falls back to until an admin overrides it.
 */
class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = self::query()->where('key', $key)->value('value');

        return $value ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        self::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }

    public static function getBool(string $key, bool $default = false): bool
    {
        $value = self::get($key);

        return $value === null ? $default : filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
