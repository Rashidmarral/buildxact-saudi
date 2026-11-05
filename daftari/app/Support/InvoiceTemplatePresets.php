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
            'bilingual_classic' => [
                'name' => 'Bilingual Classic',
                'name_ar' => 'كلاسيكي ثنائي اللغة',
                'accent_color' => '#c9a227',
                'layout' => 'bilingual_classic',
                'size' => 'A4',
            ],
            'custom_letterhead' => [
                'name' => 'Custom Letterhead',
                'name_ar' => 'ترويسة مخصصة',
                'accent_color' => '#1d4ed8',
                'layout' => 'custom_letterhead',
                'size' => 'A4',
            ],
            // Modeled on a real construction-industry price quotation a
            // user shared as a reference: logo top-left, a plain (unboxed)
            // No./Date/Hijri date/C.R. block top-right, a centered title,
            // a "Messrs. X, Peace be upon you" salutation, the items
            // table with totals appended as rows beneath it, a numbered
            // payment-terms section, a single signature+stamp+phone block,
            // and a bilingual address footer band. One preset per
            // language rather than a bilingual mix, since the reference
            // document itself commits fully to one language throughout
            // the body (only the footer address band stays bilingual,
            // matching the reference).
            'quotation_offer_ar' => [
                'name' => 'Quotation Offer (Arabic)',
                'name_ar' => 'عرض سعر',
                'accent_color' => '#0f172a',
                'layout' => 'quotation_offer',
                'language_mode' => 'arabic_only',
                'size' => 'A4',
            ],
            'quotation_offer_en' => [
                'name' => 'Quotation Offer (English)',
                'name_ar' => 'عرض سعر (إنجليزي)',
                'accent_color' => '#0f172a',
                'layout' => 'quotation_offer',
                'language_mode' => 'english_only',
                'size' => 'A4',
            ],
            // Modeled on a real Sage-generated tax invoice a user shared:
            // logo top-left with the QR beneath it, a plain Invoice
            // Number/Date/Payment Type/Customer Acc. box top-right, a
            // centered bilingual title, green-accented "Seller:"/"Buyer:"
            // key-value blocks (each carrying the full national-address
            // breakdown — building/street/district/postal code/city), an
            // items table with a bilingual header row, a right-aligned
            // totals block, the total spelled out in words, and a company
            // stamp bottom-center.
            'sage_classic' => [
                'name' => 'Sage Classic',
                'name_ar' => 'سيج كلاسيكي',
                'accent_color' => '#16a34a',
                'layout' => 'sage_classic',
                'size' => 'A4',
            ],
            // Modeled on a real retail/POS-generated tax invoice a user
            // shared: a blue-bordered Seller box and Customer box (each
            // with the full national-address breakdown), a compact info
            // strip (Invoice Ser./Date/Date of Supply/Ref No/Payment
            // Method) beside the QR, an items table that also carries a
            // per-line ZATCA tax category code, a separate VAT Summary
            // sub-table grouped by tax code, and a totals block ending in
            // Total (Excluding VAT)/Discount/Other Charges/Total Taxable
            // Amount/Total VAT/Total Amount Including VAT. The source
            // document is itself landscape with rotated column headers;
            // this reflows the same fields into portrait A4.
            'retail_classic' => [
                'name' => 'Retail Classic',
                'name_ar' => 'تجاري كلاسيكي',
                'accent_color' => '#1d4ed8',
                'layout' => 'retail_classic',
                'size' => 'A4',
            ],
            // Modeled 1:1 on a real wholesale/trading company's tax
            // invoice a user shared: plain two-column header (no boxed
            // title bar), a tan title box beside the invoice number, a
            // dashed-border info grid (Hijri/Gregorian date, time,
            // customer code/name/C.R./VAT, phone/address, payment terms/
            // supply date/ref.), an item-code-carrying items table, a
            // three-box totals grid ending in a running customer balance,
            // a bilingual amount-in-words bar, an IBAN strip, and a
            // Received-By / QR / Issued-By signature row.
            'distribution_classic' => [
                'name' => 'Distribution Classic',
                'name_ar' => 'توزيع كلاسيكي',
                'accent_color' => '#92400e',
                'layout' => 'distribution_classic',
                'size' => 'A4',
            ],
        ];
    }

    public static function find(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }
}
