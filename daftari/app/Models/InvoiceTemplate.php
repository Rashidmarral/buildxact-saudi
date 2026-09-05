<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class InvoiceTemplate extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'name', 'name_ar', 'document_type', 'accent_color',
        'layout', 'language_mode', 'table_direction', 'show_signature',
        'signature_label_en', 'signature_label_ar',
        'show_logo', 'letterhead_path', 'footer_path', 'watermark_path', 'watermark_opacity',
        'notes_en', 'notes_ar', 'is_default',
    ];

    protected function casts(): array
    {
        return [
            'show_logo' => 'boolean',
            'show_signature' => 'boolean',
            'is_default' => 'boolean',
            'watermark_opacity' => 'integer',
        ];
    }

    public function notesFor(string $locale): ?string
    {
        return $locale === 'ar' ? ($this->notes_ar ?: $this->notes_en) : $this->notes_en;
    }

    public function signatureLabelFor(string $locale): ?string
    {
        $label = $locale === 'ar' ? ($this->signature_label_ar ?: $this->signature_label_en) : $this->signature_label_en;

        return $label ?: __('Authorized Signature');
    }
}
