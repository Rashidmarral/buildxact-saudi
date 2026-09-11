<?php

use App\Models\CmsSection;
use Illuminate\Database\Migrations\Migration;

/**
 * Moves the header nav links and footer link columns — previously
 * hardcoded in layouts/site.blade.php — into admin-editable CMS blocks
 * (main_nav / footer_links, see CmsSection::TYPES), so the admin CMS can
 * add/remove/reorder/relabel them without a code change. A data migration
 * (not a seeder) for the same reason as expand_cms_content_and_add_global_
 * blocks: it must reach an existing install via a plain `php artisan
 * migrate`, not just fresh installs. Guarded by existence checks, so it's
 * a safe no-op if this content already exists or an admin has since
 * customized it.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->addMainNav();
        $this->addFooterLinks();
    }

    public function down(): void
    {
        // Deliberately no-op — see expand_cms_content_and_add_global_blocks.
    }

    private function addMainNav(): void
    {
        if (CmsSection::query()->where('page', 'global')->where('type', 'main_nav')->exists()) {
            return;
        }

        $section = CmsSection::create([
            'page' => 'global',
            'type' => 'main_nav',
            'sort_order' => 3,
            'is_active' => true,
        ]);

        $links = [
            ['Features', 'المزايا', 'features'],
            ['Pricing', 'الأسعار', 'pricing'],
            ['Compliance', 'الامتثال', 'compliance'],
            ['About', 'من نحن', 'about'],
            ['Contact', 'تواصل معنا', 'contact'],
        ];

        foreach ($links as $i => [$tEn, $tAr, $routeName]) {
            $section->items()->create([
                'sort_order' => $i + 1,
                'is_active' => true,
                'title_en' => $tEn,
                'title_ar' => $tAr,
                'meta' => ['url' => route($routeName, [], false)],
            ]);
        }
    }

    private function addFooterLinks(): void
    {
        if (CmsSection::query()->where('page', 'global')->where('type', 'footer_links')->exists()) {
            return;
        }

        $maxOrder = (int) CmsSection::query()->where('page', 'global')->max('sort_order');

        $columns = [
            'Product' => [
                'ar' => 'المنتج',
                'links' => [
                    ['Features', 'المزايا', 'features'],
                    ['Pricing', 'الأسعار', 'pricing'],
                ],
            ],
            'Tools' => [
                'ar' => 'الأدوات',
                'links' => [
                    ['All accounting tools', 'جميع أدوات المحاسبة', 'tools.index'],
                    ['VAT calculator', 'حاسبة ضريبة القيمة المضافة', 'tools.vat'],
                    ['Zakat calculator', 'حاسبة الزكاة', 'tools.zakat'],
                    ['GOSI calculator', 'حاسبة التأمينات الاجتماعية', 'tools.gosi'],
                    ['Invoice generator', 'مولّد الفواتير', 'tools.invoice-generator'],
                ],
            ],
            'Resources' => [
                'ar' => 'الموارد',
                'links' => [
                    ['Compliance', 'الامتثال', 'compliance'],
                    ['Certificates', 'الشهادات', 'certificates'],
                    ['Glossary', 'المعجم', 'glossary'],
                ],
            ],
            'Company' => [
                'ar' => 'الشركة',
                'links' => [
                    ['About', 'من نحن', 'about'],
                    ['Contact', 'تواصل معنا', 'contact'],
                    ['Terms', 'الشروط', ['legal', 'terms']],
                    ['Privacy', 'الخصوصية', ['legal', 'privacy']],
                ],
            ],
        ];

        $order = $maxOrder;
        foreach ($columns as $titleEn => $column) {
            $order++;

            $section = CmsSection::create([
                'page' => 'global',
                'type' => 'footer_links',
                'sort_order' => $order,
                'is_active' => true,
                'title_en' => $titleEn,
                'title_ar' => $column['ar'],
            ]);

            foreach ($column['links'] as $i => [$tEn, $tAr, $route]) {
                $url = is_array($route) ? route($route[0], $route[1], false) : route($route, [], false);

                $section->items()->create([
                    'sort_order' => $i + 1,
                    'is_active' => true,
                    'title_en' => $tEn,
                    'title_ar' => $tAr,
                    'meta' => ['url' => $url],
                ]);
            }
        }
    }
};
