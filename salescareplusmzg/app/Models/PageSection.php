<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageSection extends Model
{
    protected $fillable = [
        'page_id',
        'type',
        'heading',
        'subheading',
        'body',
        'image_path',
        'video_url',
        'video_path',
        'button_text',
        'button_url',
        'items',
        'background',
        'sort_order',
        'is_visible',
    ];

    protected $casts = [
        'items' => 'array',
        'is_visible' => 'boolean',
    ];

    public const TYPES = [
        'hero' => 'Hero (heading, text, button)',
        'hero_video' => 'Hero with Video Banner',
        'rich_text' => 'Rich Text Block',
        'image_text' => 'Image + Text',
        'cta' => 'Call to Action Banner',
        'cards' => 'Card Grid (title/text items)',
        'gallery' => 'Image Gallery',
    ];

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }
}
