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
        'animation',
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
        'stats' => 'Stats Row (number/label items)',
        'quote' => 'Featured Quote',
        'team' => 'Team Members (pulled live from Team Members)',
        'testimonials' => 'Testimonials (pulled live from Testimonials)',
        'faq' => 'FAQ Accordion (pulled live from FAQs)',
    ];

    public const ANIMATIONS = [
        'fade' => 'Fade Up (default)',
        'zoom' => 'Zoom In',
        'tilt' => '3D Tilt',
        'flip' => '3D Flip',
        'float' => 'Fade + Gentle Float',
        'none' => 'None',
    ];

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function animationClass(): string
    {
        return match ($this->animation) {
            'zoom' => 'reveal-scale',
            'tilt' => 'reveal-tilt',
            'flip' => 'reveal-flip',
            'float' => 'reveal animate-float',
            'none' => '',
            default => 'reveal',
        };
    }
}
