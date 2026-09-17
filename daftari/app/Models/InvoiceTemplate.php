<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class InvoiceTemplate extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'name', 'name_ar', 'document_type', 'accent_color', 'table_header_color', 'totals_color',
        'layout', 'density', 'language_mode', 'table_direction', 'show_signature',
        'signature_label_en', 'signature_label_ar',
        'show_logo', 'show_unit_labels', 'show_party_vat_number', 'show_item_description', 'show_vat_column', 'page_size',
        'letterhead_path', 'footer_path', 'watermark_path', 'watermark_opacity',
        'notes_en', 'notes_ar', 'terms_en', 'terms_ar', 'is_default',
    ];

    protected function casts(): array
    {
        return [
            'show_logo' => 'boolean',
            'show_signature' => 'boolean',
            'show_unit_labels' => 'boolean',
            'show_party_vat_number' => 'boolean',
            'show_item_description' => 'boolean',
            'show_vat_column' => 'boolean',
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
}
