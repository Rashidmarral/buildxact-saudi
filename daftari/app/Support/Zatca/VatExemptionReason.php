<?php

namespace App\Support\Zatca;

use App\Models\TaxRate;

/**
 * ZATCA's official VATEX-SA-* exemption/exception reason code list
 * (spec section 11.2.4, "VAT categories code" table) — the codes a
 * Zero-rated (Z) or Exempt (E) VAT category line is allowed to cite.
 * "Not subject to VAT" (category O) uses the single fixed VATEX-SA-OOS
 * code here, but — per the spec's own "Reason is free text, to be
 * provided by the taxpayer on case to case basis" wording — its reason
 * TEXT isn't one of these fixed strings; it's TaxRate::exemption_reason_text,
 * entered per rate (see TaxRateController).
 */
class VatExemptionReason
{
    public const LIST = [
        'VATEX-SA-OOS' => ['type' => TaxRate::TYPE_NOT_SUBJECT, 'en' => 'Reason is free text, to be provided by the taxpayer on a case-to-case basis.', 'ar' => 'السبب نص حر، يقدمه المكلف على أساس كل حالة على حدة.'],
        'VATEX-SA-29' => ['type' => TaxRate::TYPE_EXEMPT, 'en' => 'Financial services mentioned in Article 29 of the VAT Regulations', 'ar' => 'الخدمات المالية المعفاة'],
        'VATEX-SA-29-7' => ['type' => TaxRate::TYPE_EXEMPT, 'en' => 'Life insurance services mentioned in Article 29 of the VAT Regulations', 'ar' => 'عقد تأمين على الحياة'],
        'VATEX-SA-30' => ['type' => TaxRate::TYPE_EXEMPT, 'en' => 'Real estate transactions mentioned in Article 30 of the VAT Regulations', 'ar' => 'التوريدات العقارية المعفاة من الضريبة'],
        'VATEX-SA-32' => ['type' => TaxRate::TYPE_ZERO_RATED, 'en' => 'Export of goods', 'ar' => 'صادرات السلع من المملكة'],
        'VATEX-SA-33' => ['type' => TaxRate::TYPE_ZERO_RATED, 'en' => 'Export of services', 'ar' => 'صادرات الخدمات من المملكة'],
        'VATEX-SA-34-1' => ['type' => TaxRate::TYPE_ZERO_RATED, 'en' => 'The international transport of Goods', 'ar' => 'النقل الدولي للسلع'],
        'VATEX-SA-34-2' => ['type' => TaxRate::TYPE_ZERO_RATED, 'en' => 'International transport of passengers', 'ar' => 'النقل الدولي للركاب'],
        'VATEX-SA-34-3' => ['type' => TaxRate::TYPE_ZERO_RATED, 'en' => 'Services directly connected and incidental to a supply of international passenger transport', 'ar' => 'الخدمات المرتبطة مباشرة أو عرضاً بتوريد النقل الدولي للركاب'],
        'VATEX-SA-34-4' => ['type' => TaxRate::TYPE_ZERO_RATED, 'en' => 'Supply of a qualifying means of transport', 'ar' => 'توريد وسائل النقل المؤهلة'],
        'VATEX-SA-34-5' => ['type' => TaxRate::TYPE_ZERO_RATED, 'en' => 'Any services relating to Goods or passenger transportation, as defined in article twenty five of these Regulations', 'ar' => 'الخدمات ذات الصلة بنقل السلع أو الركاب'],
        'VATEX-SA-35' => ['type' => TaxRate::TYPE_ZERO_RATED, 'en' => 'Medicines and medical equipment', 'ar' => 'الأدوية والمعدات الطبية'],
        'VATEX-SA-36' => ['type' => TaxRate::TYPE_ZERO_RATED, 'en' => 'Qualifying metals', 'ar' => 'المعادن المؤهلة'],
        'VATEX-SA-EDU' => ['type' => TaxRate::TYPE_ZERO_RATED, 'en' => 'Private education to citizen', 'ar' => 'الخدمات التعليمية الخاصة للمواطنين'],
        'VATEX-SA-HEA' => ['type' => TaxRate::TYPE_ZERO_RATED, 'en' => 'Private healthcare to citizen', 'ar' => 'الخدمات الصحية الخاصة للمواطنين'],
        'VATEX-SA-MLTRY' => ['type' => TaxRate::TYPE_ZERO_RATED, 'en' => 'Supply of qualified military goods', 'ar' => 'توريد السلع العسكرية المؤهلة'],
    ];

    /**
     * BR-KSA-25/BR-KSA-49: a VATEX-SA-EDU/VATEX-SA-HEA line forces the
     * buyer's name and a national-ID-typed additional buyer ID to be
     * present — a second, document-wide requirement beyond the tax
     * category itself, flagged here so callers can enforce it too.
     */
    public const REQUIRES_BUYER_NATIONAL_ID = ['VATEX-SA-EDU', 'VATEX-SA-HEA'];

    /**
     * The codes selectable for a given TaxRate::TYPE_* — only zero_rated
     * and exempt carry a reason at all (BR-S-10 forbids one on standard).
     */
    public static function optionsFor(string $taxRateType): array
    {
        $options = [];
        foreach (self::LIST as $code => $meta) {
            if ($meta['type'] === $taxRateType) {
                $options[$code] = $code.' — '.$meta['en'];
            }
        }

        return $options;
    }

    public static function isValidFor(string $code, string $taxRateType): bool
    {
        return (self::LIST[$code]['type'] ?? null) === $taxRateType;
    }

    public static function text(string $code, string $locale = 'en'): ?string
    {
        return self::LIST[$code][$locale] ?? self::LIST[$code]['en'] ?? null;
    }
}
