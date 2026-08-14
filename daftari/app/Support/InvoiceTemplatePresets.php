<?php

namespace App\Support;

class InvoiceTemplatePresets
{
    /**
     * Six starter layouts a company can clone into an editable template.
     * "layout" picks a genuinely different rendering (see the "boxed",
     * "bordered", "minimal" branches in invoices/quotations show views) —
     * these aren't just labels, each one changes how the document renders.
     */
    public static function all(): array
    {
        return [
            'bold_branded' => [
                'name' => 'Bold Branded',
                'name_ar' => 'جريء بعلامة تجارية',
                'accent_color' => '#0f4c3a',
                'layout' => 'boxed',
                'size' => 'A4',
            ],
            'arabic_executive' => [
                'name' => 'Arabic Executive',
                'name_ar' => 'تنفيذي عربي',
                'accent_color' => '#0f766e',
                'layout' => 'bordered',
                'size' => 'A4',
            ],
            'compact_commercial' => [
                'name' => 'Compact Commercial',
                'name_ar' => 'تجاري مضغوط',
                'accent_color' => '#d97706',
                'layout' => 'bordered',
                'size' => 'A4',
            ],
            'classic_professional' => [
                'name' => 'Classic Professional',
                'name_ar' => 'كلاسيكي احترافي',
                'accent_color' => '#334155',
                'layout' => 'minimal',
                'size' => 'A4',
            ],
            'modern_minimal' => [
                'name' => 'Modern Minimal',
                'name_ar' => 'حديث بسيط',
                'accent_color' => '#0d9488',
                'layout' => 'minimal',
                'size' => 'A4',
            ],
            'corporate_gray' => [
                'name' => 'Corporate Gray',
                'name_ar' => 'رمادي مؤسسي',
                'accent_color' => '#1e293b',
                'layout' => 'boxed',
                'size' => 'A4',
            ],
        ];
    }

    public static function find(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }
}
