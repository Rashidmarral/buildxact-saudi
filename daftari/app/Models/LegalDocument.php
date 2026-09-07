<?php

namespace App\Models;

use App\Support\RichText;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegalDocument extends Model
{
    public const SLUGS = [
        'terms', 'privacy', 'cookie-policy', 'subscription-agreement',
        'refund-policy', 'data-processing-agreement', 'customer-responsibilities', 'zatca-disclaimer',
    ];

    protected $fillable = [
        'slug', 'title_en', 'title_ar', 'body_en', 'body_ar', 'status', 'requires_legal_review', 'sort_order', 'updated_by',
    ];

    protected function casts(): array
    {
        return ['requires_legal_review' => 'boolean'];
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function title(): ?string
    {
        return app()->getLocale() === 'ar' && $this->title_ar ? $this->title_ar : $this->title_en;
    }

    /** Full documents (Terms, Privacy, ...) run well past the default CMS
     * body budget sized for short marketing blurbs. */
    public const MAX_BODY_LENGTH = 100000;

    public function bodyHtml(): ?string
    {
        $raw = app()->getLocale() === 'ar' && $this->body_ar ? $this->body_ar : $this->body_en;

        return $raw ? RichText::toHtml($raw, self::MAX_BODY_LENGTH) : null;
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }
}
