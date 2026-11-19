<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformActivity extends Model
{
    protected $fillable = ['code', 'description_ar', 'description_en', 'sort_order'];

    /**
     * Arabic keywords that would suggest a registered activity covers IT,
     * software, or technology services — used by the Compliance Readiness
     * checklist to flag when NONE of the operator's registered activities
     * plausibly cover selling software commercially. This is a heuristic
     * for surfacing a real question to ask a business/legal advisor, not a
     * legal determination — see Admin\ComplianceController.
     */
    private const IT_ACTIVITY_KEYWORDS = [
        'برمج', 'تقنية المعلومات', 'حاسب', 'تطوير البرمجيات', 'تقنية', 'حلول رقمية', 'برمجيات',
        'تكنولوجيا', 'الحوسبة', 'خدمات إلكترونية', 'تجارة إلكترونية',
    ];

    public static function seemsToCoverSoftwareSales(): bool
    {
        $activities = static::query()->pluck('description_ar');

        foreach ($activities as $description) {
            foreach (self::IT_ACTIVITY_KEYWORDS as $keyword) {
                if (str_contains($description, $keyword)) {
                    return true;
                }
            }
        }

        return false;
    }
}
