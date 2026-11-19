<?php

use App\Models\CmsSection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Turns the CMS's page list from a fixed array (CmsSection::PAGES) into
 * real, admin-manageable rows — the whole point being that a super admin
 * can add a brand new marketing page from Website CMS, not just edit
 * content on the pages that shipped with the app.
 *
 * `is_system` protects the 6 pages that have a dedicated route + Blade view
 * with page-specific chrome (Pricing's plan cards, Contact's form,
 * Compliance's disclaimer, ...) plus the 'global' site-wide block holder —
 * their slug can't be renamed or deleted, since routes/web.php and
 * site/*.blade.php reference them by that exact slug. Anything an admin
 * creates afterward is a plain page rendered generically by
 * Site\CmsPageController — see that class and site/cms-page.blade.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 60)->unique();
            $table->string('name_en');
            $table->string('name_ar')->nullable();
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('show_in_footer')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $systemPages = [
            'home' => ['en' => 'Home', 'ar' => 'الرئيسية'],
            'features' => ['en' => 'Features', 'ar' => 'المزايا'],
            'pricing' => ['en' => 'Pricing', 'ar' => 'الأسعار'],
            'about' => ['en' => 'About', 'ar' => 'من نحن'],
            'compliance' => ['en' => 'Compliance', 'ar' => 'الامتثال'],
            'contact' => ['en' => 'Contact', 'ar' => 'تواصل معنا'],
            'global' => ['en' => 'Site-wide (Header & Footer)', 'ar' => 'على مستوى الموقع (الرأس والتذييل)'],
        ];

        $i = 0;
        foreach ($systemPages as $slug => $names) {
            \Illuminate\Support\Facades\DB::table('cms_pages')->insert([
                'slug' => $slug,
                'name_en' => $names['en'],
                'name_ar' => $names['ar'],
                'is_system' => true,
                'is_active' => true,
                'show_in_footer' => false,
                'sort_order' => $i++,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Any page value already present on cms_sections that somehow
        // isn't one of the 6 known system slugs (shouldn't happen — this
        // is defensive, in case a future release adds a system page
        // before this migration runs) still gets a row so nothing 404s.
        $known = array_keys($systemPages);
        $orphaned = CmsSection::query()->whereNotIn('page', $known)->distinct()->pluck('page');
        foreach ($orphaned as $slug) {
            \Illuminate\Support\Facades\DB::table('cms_pages')->insert([
                'slug' => $slug,
                'name_en' => ucfirst(str_replace(['-', '_'], ' ', $slug)),
                'name_ar' => null,
                'is_system' => true,
                'is_active' => true,
                'show_in_footer' => false,
                'sort_order' => 99,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_pages');
    }
};
