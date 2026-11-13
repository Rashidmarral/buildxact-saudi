<?php

namespace Database\Seeders;

use App\Models\PlatformActivity;
use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Seeds THIS operator's real registered identity (Dynamic Core
 * Contracting Company — CR/VAT/address confirmed by the operator) as the
 * Platform Identity settings, and their actual registered CR activity
 * codes. Deliberately NOT called from DatabaseSeeder, for the same reason
 * RealCompanySeeder isn't: this is one specific operator's real business
 * data, and shipping it in a distributable package's default `migrate
 * --seed` would put it into every buyer's database. Run it explicitly
 * and only on the operator's own instance:
 * `php artisan db:seed --class=PlatformIdentitySeeder`.
 *
 * Per-key guarded so an admin who has since customized any of these
 * values (or the placeholder identity set during earlier development —
 * "Daftari SA" / a sequential test VAT/CR / "Riyadh") keeps their edit;
 * the activities list is seeded once (skipped if any row already
 * exists), since an admin manages it going forward from the Compliance
 * admin screen. No ZATCA certification is claimed here — the operator
 * confirmed they do not currently hold one.
 */
class PlatformIdentitySeeder extends Seeder
{
    public function run(): void
    {
        $placeholders = [
            'general_platform_name' => 'Daftari',
            'platform_name' => 'Daftari SA',
            'platform_name_ar' => 'دفتري',
            'platform_vat_number' => '300012345600003',
            'platform_cr_number' => '1010101010',
            'platform_address' => 'Riyadh',
            'platform_phone' => '0500000000',
            'platform_email' => 'info@daftari.example',
        ];

        $identity = [
            'general_platform_name' => 'Dynamic Core Contracting Company',
            'platform_name' => 'Dynamic Core Contracting Company',
            'platform_name_ar' => 'شركة دايناميك كور كونتراكتينج',
            'platform_vat_number' => '314526094900003',
            'platform_cr_number' => '7053180563',
            'platform_address' => 'Building 3779, Al Umrah 2 St, Al Umrah Dist., Makkah 24414',
            'platform_phone' => '0568582270',
            'platform_email' => 'info@dynamic.com.sa',
        ];

        foreach ($identity as $key => $value) {
            $current = Setting::get($key);

            if (! Setting::isConfigured($key) || $current === ($placeholders[$key] ?? null)) {
                Setting::set($key, $value);
            }
        }

        if (PlatformActivity::query()->exists()) {
            return;
        }

        $activities = [
            ['410010', 'الإنشاءات العامة للمباني السكنية'],
            ['410021', 'الانشاءات العامة للمباني غير السكنية (مثل المدارس والمستشفيات والفنادق ....الخ)'],
            ['410022', 'إنشاء المطارات ومرافقها'],
            ['410023', 'الإنشاءات العامة للمباني الحكومية'],
            ['410030', 'إنشاءات المباني الجاهزة في المواقع'],
            ['410040', 'ترميمات المباني السكنية والغير سكنية'],
            ['421010', 'إنشاء الطرق والشوارع والارصفة ومستلزمات الطرق'],
            ['421030', 'إنشاء خطوط السكك الحديدية'],
            ['422031', 'تمديدات خطوط المياه بين المدن وداخلها وإنشاء شبكات جديدة'],
            ['422032', 'إنشاء المحطات والخطوط الرئيسية لتوزيع المياه'],
            ['422033', 'إصلاح وصيانة المحطات والشبكات والخطوط الرئيسية لتوزيع المياه'],
            ['422041', 'إنشاء قنوات الري والسقي وأبراج تخزين المياه الرئيسية'],
            ['422042', 'حفر آبار المياه الأنبوبية'],
            ['422043', 'حفر آبار المياه اليدوية'],
            ['422044', 'إصلاح وصيانة قنوات الري والسقي وأبراج تخزين المياه الرئيسية'],
            ['422045', 'إصلاح وصيانة محطات ومشاريع الصرف الصحي وشبكات المجاري والمضخات'],
            ['422046', 'إصلاح وصيانة محطات الطاقة الكهربائية والمحولات'],
            ['422047', 'إصلاح وصيانة محطات وأبراج الاتصالات السلكية واللاسلكية والرادار'],
            ['422050', 'إنشاء محطات ومشاريع الصرف الصحي وشبكات المجاري والمضخات'],
            ['422060', 'إنشاء وإقامة محطات الطاقة الكهربائية والمحولات'],
            ['429010', 'إنشاء محطات التكرير والبتروكيماويات والمصافي'],
            ['429071', 'الانشاءات العامة الرياضية وتشمل الملاعب'],
            ['429072', 'أعمال تشييد الميادين العسكرية'],
            ['429073', 'إصلاح وصيانة السدود'],
            ['429074', 'إصلاح وصيانة أرصفة الموانئ والمرافق البحرية'],
            ['432230', 'تركيب الادوات الصحية وصيانتها واصلاحها'],
            ['439020', 'أعمال تركيب السقالات'],
        ];

        foreach ($activities as $i => [$code, $descriptionAr]) {
            PlatformActivity::create([
                'code' => $code,
                'description_ar' => $descriptionAr,
                'sort_order' => $i + 1,
            ]);
        }
    }
}
