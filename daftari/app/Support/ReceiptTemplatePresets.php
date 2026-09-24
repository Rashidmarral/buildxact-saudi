<?php

namespace App\Support;

/**
 * The small, dedicated template catalog for POS/Restaurant sale receipts
 * (see documents.print.pos-receipt-pdf/pos-receipt-body) — deliberately
 * separate from InvoiceTemplatePresets: a receipt is a different
 * "document shape" (narrow, thermal-style slip) from the A4 invoice/
 * quotation gallery, so its layout keys ('receipt_compact',
 * 'receipt_detailed') are never valid choices there, and vice versa.
 * Selected the same way as everything else via InvoiceTemplate rows —
 * just always with document_type='pos_receipt' (see
 * ReceiptTemplateController), so the existing per-company default/
 * override machinery (Company::defaultTemplateFor()) already works for
 * it unchanged.
 */
class ReceiptTemplatePresets
{
    public static function all(): array
    {
        return [
            'receipt_compact_bilingual' => [
                'name' => 'Compact (Bilingual)', 'name_ar' => 'مختصر (ثنائي اللغة)',
                'layout' => 'receipt_compact', 'language_mode' => 'bilingual',
            ],
            'receipt_compact_en' => [
                'name' => 'Compact (English)', 'name_ar' => 'مختصر (إنجليزي)',
                'layout' => 'receipt_compact', 'language_mode' => 'english_only',
            ],
            'receipt_compact_ar' => [
                'name' => 'Compact (Arabic)', 'name_ar' => 'مختصر (عربي)',
                'layout' => 'receipt_compact', 'language_mode' => 'arabic_only',
            ],
            'receipt_detailed_bilingual' => [
                'name' => 'Detailed (Bilingual)', 'name_ar' => 'مفصّل (ثنائي اللغة)',
                'layout' => 'receipt_detailed', 'language_mode' => 'bilingual',
            ],
        ];
    }

    public static function find(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }
}
