<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One content block on one public marketing page (home, features, pricing,
 * about, compliance, contact) — a hero, a stats bar, a feature grid, an FAQ
 * list, testimonials, a CTA banner, contact info, or social links. See
 * TYPES for the fixed set of block kinds the admin CMS and public blade
 * partials both switch on, and CmsSectionItem for a section's repeatable
 * children (feature cards, FAQ entries, ...).
 */
class CmsSection extends Model
{
    /**
     * 'global' isn't a real routed page — it holds site-wide blocks (the
     * header announcement bar, the footer tagline) rendered on every page
     * via layouts/site.blade.php rather than a single site/*.blade.php view.
     */
    public const PAGES = ['home', 'features', 'pricing', 'about', 'compliance', 'contact', 'global'];

    /**
     * type => whether this block has repeatable CmsSectionItem children.
     */
    public const TYPES = [
        'hero' => false,
        'text' => false,
        'stats' => true,
        'feature_grid' => true,
        'testimonials' => true,
        'faq' => true,
        'contact_info' => true,
        'social_links' => true,
        'cta' => false,
        'site_header' => false,
        'site_footer' => false,
    ];

    /**
     * Types whose admin form offers an image upload + left/right placement
     * (see App\Http\Controllers\Admin\CmsController::updateSection).
     */
    public const IMAGE_TYPES = ['hero', 'text', 'cta'];

    /**
     * Friendly label for a PAGES entry — every value works with ucfirst()
     * except 'global', which isn't a real page name a visitor would
     * recognize.
     */
    public static function pageLabel(string $page): string
    {
        return $page === 'global' ? __('Site-wide (Header & Footer)') : ucfirst($page);
    }

    protected $fillable = [
        'page', 'type', 'sort_order', 'is_active',
        'badge_en', 'badge_ar', 'title_en', 'title_ar', 'subtitle_en', 'subtitle_ar', 'body_en', 'body_ar',
        'image_path', 'image_position', 'link_url', 'link_text_en', 'link_text_ar',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(CmsSectionItem::class)->orderBy('sort_order');
    }

    public function hasItems(): bool
    {
        return self::TYPES[$this->type] ?? false;
    }

    public function badge(): ?string
    {
        return $this->localized('badge');
    }

    public function title(): ?string
    {
        return $this->localized('title');
    }

    public function subtitle(): ?string
    {
        return $this->localized('subtitle');
    }

    public function body(): ?string
    {
        return $this->localized('body');
    }

    public function linkText(): ?string
    {
        return $this->localized('link_text');
    }

    /**
     * The one active site-wide block of a given type (site_header or
     * site_footer) — cached per-request via a static local since
     * layouts/site.blade.php resolves both on literally every page load.
     */
    public static function globalBlock(string $type): ?self
    {
        static $cache = [];

        if (! array_key_exists($type, $cache)) {
            $cache[$type] = self::query()
                ->where('page', 'global')
                ->where('type', $type)
                ->where('is_active', true)
                ->first();
        }

        return $cache[$type];
    }

    /**
     * Active, ordered sections for one public page, with active items
     * eager-loaded — the one query every site/*.blade.php view needs.
     */
    public static function forPage(string $page)
    {
        return self::query()
            ->where('page', $page)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->with(['items' => fn ($q) => $q->where('is_active', true)])
            ->get();
    }

    private function localized(string $field): ?string
    {
        $locale = app()->getLocale();
        $localized = $this->{"{$field}_{$locale}"} ?? null;

        return filled($localized) ? $localized : $this->{"{$field}_en"};
    }
}
