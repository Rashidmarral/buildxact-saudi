<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Translation;
use App\Support\DbOverlayTranslationLoader;
use App\Support\Locales;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * "Languages" in the admin CMS — lets a super admin override any of the
 * app's translation strings (marketing site, user panel, admin panel — every
 * __() call in the codebase) without a deploy. The English source strings
 * and their shipped Arabic translations in lang/ar.json stay the read-only
 * baseline; a row in the `translations` table overrides one locale's value
 * for one key, applied everywhere via DbOverlayTranslationLoader.
 */
class TranslationController extends Controller
{
    private const PER_PAGE = 30;

    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $page = max(1, (int) $request->query('page', 1));

        $arDefaults = self::arDefaults();
        $enOverrides = self::overridesFor('en');
        $arOverrides = self::overridesFor('ar');

        $keys = array_unique(array_merge(
            array_keys($arDefaults),
            array_keys($enOverrides),
            array_keys($arOverrides)
        ));
        sort($keys, SORT_STRING);

        if ($search !== '') {
            $needle = Str::lower($search);
            $keys = array_values(array_filter($keys, function (string $key) use ($needle, $arDefaults, $enOverrides, $arOverrides) {
                return str_contains(Str::lower($key), $needle)
                    || str_contains(Str::lower($arDefaults[$key] ?? ''), $needle)
                    || str_contains(Str::lower($enOverrides[$key] ?? ''), $needle)
                    || str_contains(Str::lower($arOverrides[$key] ?? ''), $needle);
            }));
        }

        $total = count($keys);
        $slice = array_slice($keys, (max(1, $page) - 1) * self::PER_PAGE, self::PER_PAGE);

        $rows = array_map(fn (string $key) => [
            'key' => $key,
            'en_default' => $key,
            'en_override' => $enOverrides[$key] ?? null,
            'ar_default' => $arDefaults[$key] ?? null,
            'ar_override' => $arOverrides[$key] ?? null,
        ], $slice);

        $paginator = new LengthAwarePaginator($rows, $total, self::PER_PAGE, $page, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);

        return view('admin.translations.index', [
            'rows' => $paginator,
            'search' => $search,
            'totalKeys' => count($arDefaults),
            'overriddenCount' => count($enOverrides) + count($arOverrides),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'key' => ['required', 'string'],
            'en_value' => ['nullable', 'string', 'max:5000'],
            'ar_value' => ['nullable', 'string', 'max:5000'],
        ]);

        $key = $data['key'];

        foreach (Locales::codes() as $locale) {
            $field = $locale.'_value';
            $value = trim((string) ($data[$field] ?? ''));

            if ($value === '') {
                Translation::clear($locale, '*', $key);
            } else {
                Translation::upsert($locale, '*', $key, $value);
            }

            DbOverlayTranslationLoader::forget($locale, '*');
        }

        Cache::forget('admin.translations.ar_defaults');

        AuditLog::record('translations.update', null, __('Updated translation for: :key', ['key' => Str::limit($key, 80)]));

        return back()->with('status', __('Translation saved.'));
    }

    /**
     * The shipped Arabic strings from lang/ar.json — cached per-request-cycle
     * (not indefinitely, unlike the override cache) since this only reads a
     * file that ships with the app and never changes at runtime.
     */
    private static function arDefaults(): array
    {
        return Cache::remember('admin.translations.ar_defaults', 3600, function () {
            $path = base_path('lang/ar.json');

            if (! is_file($path)) {
                return [];
            }

            return json_decode(file_get_contents($path), true) ?: [];
        });
    }

    private static function overridesFor(string $locale): array
    {
        return Translation::query()
            ->where('locale', $locale)
            ->where('group', '*')
            ->pluck('value', 'key')
            ->all();
    }
}
