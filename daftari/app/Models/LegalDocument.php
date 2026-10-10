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

    /**
     * Full titles (e.g. "Data Processing & Confidentiality Terms") are too
     * long for a footer column. Keyed by the fixed slug rather than the
     * admin-editable title, so an operator renaming the document doesn't
     * silently lose its short label.
     */
    private const SHORT_LABELS = [
        'terms' => ['en' => 'Terms', 'ar' => 'الشروط'],
        'privacy' => ['en' => 'Privacy', 'ar' => 'الخصوصية'],
        'cookie-policy' => ['en' => 'Cookies', 'ar' => 'الكوكيز'],
        'subscription-agreement' => ['en' => 'Subscription', 'ar' => 'الاشتراك'],
        'refund-policy' => ['en' => 'Refunds', 'ar' => 'الاسترداد'],
        'data-processing-agreement' => ['en' => 'Data Processing', 'ar' => 'معالجة البيانات'],
        'customer-responsibilities' => ['en' => 'Responsibilities', 'ar' => 'المسؤوليات'],
        'zatca-disclaimer' => ['en' => 'ZATCA Disclaimer', 'ar' => 'إخلاء المسؤولية'],
    ];

    public function shortTitle(): string
    {
        $label = self::SHORT_LABELS[$this->slug] ?? null;

        if (! $label) {
            return $this->title() ?? $this->slug;
        }

        return app()->getLocale() === 'ar' ? $label['ar'] : $label['en'];
    }
}
