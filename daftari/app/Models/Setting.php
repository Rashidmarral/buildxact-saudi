<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

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

    protected $fillable = ['key', 'value', 'is_encrypted'];

    protected function casts(): array
    {
        return [
            'is_encrypted' => 'boolean',
        ];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $row = self::query()->where('key', $key)->first();

        if (! $row || $row->value === null) {
            return $default;
        }

        if (! $row->is_encrypted) {
            return $row->value;
        }

        try {
            return Crypt::decryptString($row->value);
        } catch (DecryptException) {
            // A changed APP_KEY (or corrupted row) makes an encrypted value
            // unreadable — fail back to the default rather than a 500, same
            // as every other outbound-integration failure mode in this app.
            return $default;
        }
    }

    public static function set(string $key, mixed $value, bool $encrypted = false): void
    {
        self::query()->updateOrCreate(['key' => $key], [
            'value' => $encrypted && $value !== null && $value !== '' ? Crypt::encryptString((string) $value) : $value,
            'is_encrypted' => $encrypted,
        ]);
    }

    public static function getBool(string $key, bool $default = false): bool
    {
        $value = self::get($key);

        return $value === null ? $default : filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Whether $key currently holds a real (non-empty) value — used by
     * settings screens to show "•••• configured" for a secret instead of
     * ever re-displaying or re-transmitting it to the browser.
     */
    public static function isConfigured(string $key): bool
    {
        return filled(self::get($key));
    }
}
