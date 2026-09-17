<?php

namespace App\Support;

use App\Models\Translation;
use Illuminate\Contracts\Translation\Loader;
use Illuminate\Support\Facades\Cache;

/**
 * Wraps Laravel's normal file-based translation loader so an admin-entered
 * Translation row (locale, group, key) overrides the file value (lang/*.json,
 * lang/{locale}/*.php) for that same key, without touching how the file
 * translations themselves are authored or shipped.
 *
 * The DB read is cached indefinitely per (locale, group) and invalidated by
 * Admin\TranslationController on save — see forgetCache(). Every call is
 * wrapped in try/catch because this loader also runs before the app is
 * installed (the 'translations' table doesn't exist yet) and whenever the
 * configured database is unreachable — in both cases translation must keep
 * working from the shipped files alone rather than fail the whole request.
 */
class DbOverlayTranslationLoader implements Loader
{
    public function __construct(private readonly Loader $files)
    {
    }

    public function load($locale, $group, $namespace = null)
    {
        $lines = $this->files->load($locale, $group, $namespace);

        if ($namespace !== null && $namespace !== '*') {
            return $lines;
        }

        try {
            $overrides = Cache::rememberForever(
                self::cacheKey($locale, $group),
                fn () => Translation::query()
                    ->where('locale', $locale)
                    ->where('group', $group)
                    ->pluck('value', 'key')
                    ->all()
            );
        } catch (\Throwable) {
            return $lines;
        }

        return array_replace($lines, $overrides);
    }

    public function addNamespace($namespace, $hint)
    {
        $this->files->addNamespace($namespace, $hint);
    }

    public function addJsonPath($path)
    {
        $this->files->addJsonPath($path);
    }

    public function namespaces()
    {
        return $this->files->namespaces();
    }

    public static function cacheKey(string $locale, string $group): string
    {
        return "translations:overrides:{$locale}:{$group}";
    }

    public static function forget(string $locale, string $group): void
    {
        Cache::forget(self::cacheKey($locale, $group));
    }
}
