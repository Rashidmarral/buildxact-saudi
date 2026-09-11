<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One repeatable child of a CmsSection — a feature tile, an FAQ entry, a
 * testimonial, a stat number, a contact method, a social link. See the
 * cms_section_items migration for why this single table serves every
 * section type instead of one table per type.
 */
class CmsSectionItem extends Model
{
    protected $fillable = [
        'cms_section_id', 'sort_order', 'is_active',
        'icon', 'image_path',
        'title_en', 'title_ar', 'subtitle_en', 'subtitle_ar', 'body_en', 'body_ar',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'meta' => 'array',
        ];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(CmsSection::class, 'cms_section_id');
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

    /**
     * @see \App\Support\RichText::toHtml()
     */
    public function bodyHtml(): ?string
    {
        return \App\Support\RichText::toHtml($this->body());
    }

    private function localized(string $field): ?string
    {
        $locale = app()->getLocale();
        $localized = $this->{"{$field}_{$locale}"} ?? null;

        return filled($localized) ? $localized : $this->{"{$field}_en"};
    }
}
