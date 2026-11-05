<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A single admin-entered override for one translation string in one locale.
 * See DbOverlayTranslationLoader for how these are merged over the
 * file-based translations (lang/ar.json etc.) that ship with the app.
 */
class Translation extends Model
{
    protected $fillable = ['locale', 'group', 'key', 'key_hash', 'value'];

    public static function hashFor(string $locale, string $group, string $key): string
    {
        return sha1($locale.'|'.$group.'|'.$key);
    }

    public static function upsert(string $locale, string $group, string $key, string $value): void
    {
        self::query()->updateOrCreate(
            ['locale' => $locale, 'group' => $group, 'key_hash' => self::hashFor($locale, $group, $key)],
            ['key' => $key, 'value' => $value]
        );
    }

    public static function clear(string $locale, string $group, string $key): void
    {
        self::query()
            ->where('locale', $locale)
            ->where('group', $group)
            ->where('key_hash', self::hashFor($locale, $group, $key))
            ->delete();
    }
}
