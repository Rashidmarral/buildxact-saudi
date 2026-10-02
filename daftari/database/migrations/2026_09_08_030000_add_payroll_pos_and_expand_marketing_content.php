<?php

use App\Models\CmsSection;
use Illuminate\Database\Migrations\Migration;

/**
 * Data migration (not a seeder) for the same reason as the header/footer
 * one it follows: it must reach an existing install via a plain
 * `php artisan migrate`, not just fresh installs. Mirrors the additions
 * made to CmsContentSeeder — keep both in sync.
 *
 * Adds the new Payroll and POS modules to the home/features feature grids,
 * removes the now-stale "couple of items still in progress" claim from the
 * Features hero (both modules are fully built), and gives the About and
 * Contact pages more substance — a milestones strip and testimonials on
 * About, a fourth contact channel and two more FAQ entries on Contact.
 *
 * Every write below is guarded by an existence check on the specific
 * content being added, so it's a safe no-op on a second run and never
 * clobbers content an admin has since edited via Website CMS.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->addPayrollPosFeature('home');
        $this->addPayrollPosFeature('features');
        $this->fixFeaturesHeroSubtitle();
        $this->expandAbout();
        $this->expandContact();
    }

    public function down(): void
    {
        // Deliberately no-op — see add_header_nav_and_footer_links_cms_blocks.
    }

    private function payrollPosRows(): array
    {
        return [
            ['🧮', 'Full payroll, GOSI & WPS', 'رواتب كاملة والتأمينات ونظام حماية الأجور', 'Employee records, monthly payroll runs with GOSI employee/employer contributions, WPS bank-file export, payslips, and end-of-service gratuity — all posted to your books automatically.', 'سجلات الموظفين ودورات رواتب شهرية مع اشتراكات التأمينات الاجتماعية للموظف وصاحب العمل، وملف بنكي لنظام حماية الأجور، وقسائم رواتب، ومكافأة نهاية الخدمة — تُرحّل جميعها تلقائيًا إلى سجلاتك المحاسبية.'],
            ['🛒', 'Point of sale (POS)', 'نقطة البيع', 'A touch-friendly checkout for retail counters — barcode scan, split cash/card payments, register shifts with a Z-report reconciliation, and a ZATCA-compliant QR receipt on every sale.', 'شاشة دفع سريعة الاستخدام لمنافذ البيع بالتجزئة — مسح الباركود، ودفع مقسّم نقدًا وبالبطاقة، وورديات صندوق مع تسوية تقرير Z، وإيصال بيع يتضمن رمز QR متوافقًا مع هيئة الزكاة والضريبة والجمارك.'],
        ];
    }

    private function addPayrollPosFeature(string $page): void
    {
        $section = CmsSection::query()->where('page', $page)->where('type', 'feature_grid')->first();

        if (! $section) {
            return;
        }

        $maxOrder = (int) $section->items()->max('sort_order');

        foreach ($this->payrollPosRows() as $i => [$icon, $tEn, $tAr, $bEn, $bAr]) {
            if ($section->items()->where('title_en', $tEn)->exists()) {
                continue;
            }

            $section->items()->create([
                'sort_order' => $maxOrder + $i + 1,
                'is_active' => true,
                'icon' => $icon,
                'title_en' => $tEn,
                'title_ar' => $tAr,
                'body_en' => $bEn,
                'body_ar' => $bAr,
            ]);
        }
    }

    private function fixFeaturesHeroSubtitle(): void
    {
        $hero = CmsSection::query()->where('page', 'features')->where('type', 'hero')->first();

        if (! $hero || $hero->subtitle_en !== 'Daftari brings invoicing, expenses, purchasing, inventory, accounting, and VAT reporting together for Saudi businesses. Almost everything below is live today; the couple of items still in progress are clearly marked.') {
            return;
        }

        $hero->update([
            'subtitle_en' => 'Daftari brings invoicing, expenses, purchasing, inventory, accounting, payroll, point of sale, and VAT reporting together for Saudi businesses — one connected platform instead of a patchwork of tools. Everything below is live today.',
            'subtitle_ar' => 'يجمع دفتري بين الفوترة والمصروفات والمشتريات والمخزون والمحاسبة والرواتب ونقطة البيع وتقارير ضريبة القيمة المضافة في منصة واحدة متكاملة للأعمال السعودية، بدلًا من أدوات متفرقة. كل ما يظهر أدناه متاح اليوم بالكامل.',
        ]);
    }

    private function expandAbout(): void
    {
        if (! CmsSection::query()->where('page', 'about')->where('type', 'stats')->exists()) {
            $maxOrder = (int) CmsSection::query()->where('page', 'about')->max('sort_order');

            $stats = CmsSection::create([
                'page' => 'about', 'type' => 'stats', 'sort_order' => $maxOrder + 1, 'is_active' => true,
                'title_en' => 'One platform, every module', 'title_ar' => 'منصة واحدة، بكل الوحدات',
            ]);
            $rows = [
                ['10+', '10+', 'Connected modules', 'وحدات مترابطة'],
                ['Phase 1 & 2', 'المرحلتان 1 و2', 'ZATCA e-invoicing', 'الفوترة الإلكترونية لهيئة الزكاة'],
                ['GOSI & WPS', 'التأمينات ونظام الأجور', 'Compliant payroll', 'رواتب متوافقة مع الأنظمة'],
                ['AR / EN', 'عربي / إنجليزي', 'Fully bilingual, RTL-ready', 'ثنائي اللغة بالكامل وجاهز لليمين-يسار'],
            ];
            foreach ($rows as $i => [$tEn, $tAr, $sEn, $sAr]) {
                $stats->items()->create(['sort_order' => $i + 1, 'is_active' => true, 'title_en' => $tEn, 'title_ar' => $tAr, 'subtitle_en' => $sEn, 'subtitle_ar' => $sAr]);
            }
        }

        if (! CmsSection::query()->where('page', 'about')->where('type', 'testimonials')->exists()) {
            $maxOrder = (int) CmsSection::query()->where('page', 'about')->max('sort_order');

            $testimonials = CmsSection::create([
                'page' => 'about', 'type' => 'testimonials', 'sort_order' => $maxOrder + 1, 'is_active' => true,
                'title_en' => 'What business owners say', 'title_ar' => 'ماذا يقول أصحاب الأعمال',
            ]);
            $rows = [
                ['We moved our invoicing, purchasing, and VAT reporting into Daftari in an afternoon. Having payroll and POS in the same place now means our accountant only looks in one place at month end.', 'نقلنا فوترتنا ومشترياتنا وتقارير ضريبة القيمة المضافة إلى دفتري في يوم واحد. وجود الرواتب ونقطة البيع في نفس المكان الآن يعني أن محاسبنا ينظر في مكان واحد فقط في نهاية الشهر.', 'Finance Manager', 'مدير مالي', 'Contracting business', 'شركة مقاولات'],
                ['The GOSI and WPS calculations used to take our bookkeeper a full day every month. Now the payroll run does it in minutes and posts straight to the books.', 'كانت حسابات التأمينات ونظام حماية الأجور تستغرق من محاسبنا يومًا كاملًا كل شهر. الآن تتم دورة الرواتب خلال دقائق وتُرحّل مباشرة إلى السجلات.', 'Owner', 'صاحب المنشأة', 'Retail chain', 'سلسلة متاجر تجزئة'],
                ['Our cashiers picked up the POS screen in minutes, and every receipt already carries a proper ZATCA QR code — one less thing to worry about.', 'تعلّم أمناء الصندوق لدينا استخدام شاشة نقطة البيع خلال دقائق، وكل إيصال يحمل رمز QR متوافقًا مع هيئة الزكاة والضريبة — أمر أقل نقلقه.', 'Operations Lead', 'مسؤول العمليات', 'Food & beverage outlet', 'منفذ أغذية ومشروبات'],
            ];
            foreach ($rows as $i => [$bEn, $bAr, $tEn, $tAr, $sEn, $sAr]) {
                $testimonials->items()->create(['sort_order' => $i + 1, 'is_active' => true, 'title_en' => $tEn, 'title_ar' => $tAr, 'subtitle_en' => $sEn, 'subtitle_ar' => $sAr, 'body_en' => $bEn, 'body_ar' => $bAr]);
            }
        }
    }

    private function expandContact(): void
    {
        $methods = CmsSection::query()->where('page', 'contact')->where('type', 'contact_info')->first();

        if ($methods && ! $methods->items()->where('title_en', 'Sales')->exists()) {
            $maxOrder = (int) $methods->items()->max('sort_order');
            $methods->items()->create([
                'sort_order' => $maxOrder + 1, 'is_active' => true, 'icon' => '🤝',
                'title_en' => 'Sales', 'title_ar' => 'المبيعات',
                'subtitle_en' => 'Questions before you buy, or need a plan recommendation', 'subtitle_ar' => 'أسئلة قبل الشراء أو تحتاج توصية بالباقة المناسبة',
                'body_en' => 'sales@daftari.app', 'body_ar' => 'sales@daftari.app',
                'meta' => ['url' => 'mailto:sales@daftari.app'],
            ]);
        }

        $faq = CmsSection::query()->where('page', 'contact')->where('type', 'faq')->first();

        if ($faq) {
            $maxOrder = (int) $faq->items()->max('sort_order');
            $rows = [
                ['Does Daftari include payroll and a point of sale?', 'هل يشمل دفتري الرواتب ونقطة البيع؟', 'Yes — full WPS-compliant payroll with GOSI and end-of-service calculations, and a retail point of sale with split payments and ZATCA receipts, are both built into the platform on qualifying plans.', 'نعم — الرواتب الكاملة المتوافقة مع نظام حماية الأجور مع حسابات التأمينات ومكافأة نهاية الخدمة، ونقطة بيع للتجزئة مع دفع مقسّم وإيصالات متوافقة مع هيئة الزكاة، كلاهما مدمج في المنصة ضمن الباقات المؤهلة.'],
                ['Can I try Daftari with my team before deciding?', 'هل يمكنني تجربة دفتري مع فريقي قبل اتخاذ القرار؟', 'Yes — every plan starts with a free trial, no credit card required, so you and your team can try real invoicing, payroll, and POS workflows before you commit.', 'نعم — تبدأ كل باقة بتجربة مجانية دون الحاجة لبطاقة ائتمان، حتى تتمكن أنت وفريقك من تجربة سير عمل حقيقي للفوترة والرواتب ونقطة البيع قبل الالتزام.'],
            ];
            foreach ($rows as $i => [$qEn, $qAr, $aEn, $aAr]) {
                if ($faq->items()->where('title_en', $qEn)->exists()) {
                    continue;
                }
                $faq->items()->create(['sort_order' => $maxOrder + $i + 1, 'is_active' => true, 'title_en' => $qEn, 'title_ar' => $qAr, 'body_en' => $aEn, 'body_ar' => $aAr]);
            }
        }
    }
};
