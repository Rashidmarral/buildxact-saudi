<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class InvoiceTemplate extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'name', 'name_ar', 'document_type', 'accent_color',
        'layout', 'show_logo', 'letterhead_path', 'notes_en', 'notes_ar', 'is_default',
    ];

    protected function casts(): array
    {
        return [
            'show_logo' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    public function notesFor(string $locale): ?string
    {
        return $locale === 'ar' ? ($this->notes_ar ?: $this->notes_en) : $this->notes_en;
    }
}
