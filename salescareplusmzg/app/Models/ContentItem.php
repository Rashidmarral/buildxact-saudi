<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A single reusable model for the small repeatable content lists sprinkled
 * across Services/Careers/Quality/About — service cards, "how it works"
 * steps, job openings, quality standards, and about-page highlights/values.
 * Distinguished by `group`; see Admin\ContentItemController::GROUPS for the
 * canonical list and how each group's fields are labelled/used.
 */
class ContentItem extends Model
{
    protected $fillable = [
        'group',
        'icon',
        'title',
        'subtitle',
        'description',
        'sort_order',
        'is_visible',
    ];

    protected $casts = [
        'is_visible' => 'boolean',
    ];

    public function scopeGroup($query, string $group)
    {
        return $query->where('group', $group);
    }

    public function scopeVisible($query)
    {
        return $query->where('is_visible', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }
}
