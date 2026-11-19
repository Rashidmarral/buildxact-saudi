<?php

use App\Models\CmsSection;
use Illuminate\Database\Migrations\Migration;

/**
 * "ZATCA-ready e-invoicing" reads as a stronger claim than intended — it
 * can be misread as ZATCA having approved or certified the product,
 * which nothing in this codebase substantiates. Softens it to describe
 * what's actually true: the software is built to support ZATCA's
 * published e-invoicing requirements. Guarded to the exact original
 * text, so an admin who has since edited this badge keeps their wording.
 */
return new class extends Migration
{
    public function up(): void
    {
        CmsSection::query()
            ->where('page', 'home')->where('type', 'hero')
            ->where('badge_en', 'ZATCA-ready e-invoicing')
            ->update([
                'badge_en' => 'Built to support ZATCA e-invoicing',
                'badge_ar' => 'مصمم لدعم متطلبات الفوترة الإلكترونية لهيئة الزكاة والضريبة والجمارك',
            ]);
    }

    public function down(): void
    {
        // Deliberately no-op — see other data migrations in this batch.
    }
};
