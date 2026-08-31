<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A public marketing page — either one of the 6 built-in pages shipped
 * with the app (is_system = true, has a dedicated route + Blade view with
 * page-specific chrome) or a page a super admin created from Website CMS
 * (is_system = false, rendered generically by Site\CmsPageController at
 * /pages/{slug} — see site/cms-page.blade.php). Either way its content is
 * the same CmsSection/CmsSectionItem rows, looked up by matching `slug`
 * against CmsSection::page.
 */
class CmsPage extends Model
{
    /**
     * Slugs no custom page may claim — either already a system page, or a
     * root-level marketing route (see routes/web.php) that /pages/{slug}
     * would otherwise shadow confusingly.
     */
    public const RESERVED_SLUGS = [
        'home', 'features', 'pricing', 'about', 'compliance', 'contact', 'global',
        'certificates', 'glossary', 'legal', 'tools', 'login', 'register', 'admin', 'app',
    ];

    protected $fillable = [
        'slug', 'name_en', 'name_ar', 'is_system', 'is_active', 'show_in_footer', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_active' => 'boolean',
            'show_in_footer' => 'boolean',
        ];
    }

    public function sections(): HasMany
    {
        return $this->hasMany(CmsSection::class, 'page', 'slug');
    }

    public function name(): string
    {
        $locale = app()->getLocale();

        return ($locale === 'ar' ? $this->name_ar : null) ?: $this->name_en;
    }

    /**
     * Null for the 'global' system row — it has no page of its own, it's
     * the header/footer blocks shown on every page.
     */
    public function publicUrl(): ?string
    {
        if ($this->slug === 'global') {
            return null;
        }

        return $this->is_system ? route($this->slug) : route('cms-page.show', $this->slug);
    }
}
