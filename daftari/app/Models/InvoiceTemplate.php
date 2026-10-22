<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class InvoiceTemplate extends Model
{
    use BelongsToCompany;

    /** Google Fonts family names selectable for the browser show page — see font_family's own doc block below. */
    public const FONT_FAMILIES = ['cairo', 'tajawal', 'ibm_plex_sans_arabic', 'noto_naskh_arabic', 'inter'];

    public const FONT_SIZES = ['small', 'medium', 'large'];

    public const BORDER_STYLES = ['none', 'thin', 'thick', 'double'];

    protected $fillable = [
        'company_id', 'design_group', 'name', 'name_ar', 'document_type', 'preset_key', 'accent_color', 'table_header_color', 'totals_color',
        'layout', 'font_family', 'font_size', 'border_style', 'density', 'language_mode', 'table_direction', 'show_signature',
        'signature_label_en', 'signature_label_ar',
        'show_greeting', 'greeting_en', 'greeting_ar',
        'show_logo', 'show_unit_labels', 'show_party_vat_number', 'show_item_description', 'show_vat_column', 'page_size',
        'letterhead_path', 'footer_path', 'watermark_path', 'watermark_opacity',
        'notes_en', 'notes_ar', 'terms_en', 'terms_ar', 'is_default',
    ];

    protected static function booted(): void
    {
        // Every template is "a design" — one that happens to apply to a
        // single document type when created on its own, or to several at
        // once when created through the multi-select "Applies to" picker
        // (see InvoiceTemplateController). A design_group ties those
        // sibling rows together without changing what any individual row
        // means to the rest of the app (document_type-scoped, exactly as
        // before) — this just guarantees the handle always exists, even
        // for a row inserted some path other than the controller (a
        // seeder, Eloquent factory, tinker) forgets to set it.
        static::creating(function (self $template) {
            if (empty($template->design_group)) {
                $template->design_group = (string) Str::uuid();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'show_logo' => 'boolean',
            'show_signature' => 'boolean',
            'show_unit_labels' => 'boolean',
            'show_party_vat_number' => 'boolean',
            'show_item_description' => 'boolean',
            'show_vat_column' => 'boolean',
            'show_greeting' => 'boolean',
            'is_default' => 'boolean',
            'watermark_opacity' => 'integer',
        ];
    }

    public function notesFor(string $locale): ?string
    {
        return $locale === 'ar' ? ($this->notes_ar ?: $this->notes_en) : $this->notes_en;
    }

    public function termsFor(string $locale): ?string
    {
        return $locale === 'ar' ? ($this->terms_ar ?: $this->terms_en) : $this->terms_en;
    }

    public function signatureLabelFor(string $locale): ?string
    {
        $label = $locale === 'ar' ? ($this->signature_label_ar ?: $this->signature_label_en) : $this->signature_label_en;

        return $label ?: __('Authorized Signature');
    }

    /** Falls back to the shared accent color when no separate totals
     * color has been chosen — most companies want one brand color. */
    public function totalsColor(): string
    {
        return $this->totals_color ?: ($this->accent_color ?: '#0f766e');
    }

    public function isCompact(): bool
    {
        return $this->density !== 'comfortable';
    }

    /**
     * The browser show page loads this family from Google Fonts directly
     * (no asset/licensing work needed — it's just a stylesheet link, the
     * same way any web page picks a font). The downloaded PDF always
     * renders with the one font mPDF has embedded with verified Arabic
     * shaping (OTL + kashida — see MpdfRenderer's own fontdata comment);
     * a second embedded, bilingual-safe font is real future work, not
     * something to risk on a live tax-invoice PDF without visually
     * verifying every glyph it needs to draw first. null/"cairo" both
     * mean "use the default," so an unset template needs no special
     * casing here.
     */
    public function googleFontFamily(): ?string
    {
        return match ($this->font_family) {
            'tajawal' => 'Tajawal:wght@400;500;700',
            'ibm_plex_sans_arabic' => 'IBM+Plex+Sans+Arabic:wght@400;500;700',
            'noto_naskh_arabic' => 'Noto+Naskh+Arabic:wght@400;500;700',
            'inter' => 'Inter:wght@400;500;700',
            default => null,
        };
    }

    public function cssFontFamily(): string
    {
        return match ($this->font_family) {
            'tajawal' => "'Tajawal', sans-serif",
            'ibm_plex_sans_arabic' => "'IBM Plex Sans Arabic', sans-serif",
            'noto_naskh_arabic' => "'Noto Naskh Arabic', serif",
            'inter' => "'Inter', sans-serif",
            default => "'Cairo', sans-serif",
        };
    }

    /** A base text-size nudge — see this migration's own doc block for why this doesn't try to uniformly rescale every already-tuned element. */
    public function fontSizeScale(): float
    {
        return match ($this->font_size) {
            'small' => 0.9,
            'large' => 1.15,
            default => 1.0,
        };
    }

    /** CSS border shorthand for the outer frame around the whole printed document, or null for no frame. */
    public function borderStyleCss(?string $color = null): ?string
    {
        $color = $color ?: ($this->accent_color ?: '#0f766e');

        return match ($this->border_style) {
            'thin' => "1px solid {$color}",
            'thick' => "3px solid {$color}",
            'double' => "4px double {$color}",
            default => null,
        };
    }
}
