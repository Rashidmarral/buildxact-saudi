<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Company;
use Illuminate\Database\Seeder;

/**
 * Real customer list for Abdulmajeed Abdullah Al-Zubaidi Est. for
 * Maintenance (see RealCompanySeeder::seedZubaidiMaintenance()), imported
 * from the operator's own accounting export. Deliberately not called from
 * DatabaseSeeder for the same reason RealCompanySeeder isn't — this is one
 * operator's real customer names, tax numbers, and contact details, not
 * demo data. Run explicitly on the operator's own instance only:
 * `php artisan db:seed --class=ZubaidiClientsSeeder`.
 *
 * Idempotent: keyed on (company_id, name) via updateOrCreate, so re-running
 * this after editing a row updates it in place instead of duplicating it.
 *
 * A few rows from the source export were left out on purpose:
 * - "Unknown" (no identifying data at all)
 * - Two rows explicitly flagged IsCustomer=0 in the export (suppliers, not
 *   customers, mixed into that list)
 * Placeholder values the source system used for "not provided" (NA, 0,
 * "لايوجد", "--", "١") were normalized to null rather than stored literally.
 * A couple of rows had a Commercial Registration number embedded in the
 * wrong export column ("رقم سجل تجاري: ...") — that's extracted into
 * cr_number instead of being dropped or stored as a bogus state/province.
 */
class ZubaidiClientsSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::where('vat_number', '310464560600003')->first();

        if (! $company) {
            $this->command?->warn('Zubaidi Maintenance company not found — run RealCompanySeeder first.');

            return;
        }

        $clients = [
            ['name' => 'مؤسسة الرباعية المتحدة للمقاولات', 'vat_number' => '311658888100003', 'cr_number' => null, 'street_name' => 'عام', 'building_number' => '6547', 'district' => 'حي ابراق الرغامة', 'city' => 'جدة', 'state' => null, 'postal_code' => '22262', 'email' => null, 'phone' => null, 'notes' => 'Plot ID: 3649'],
            ['name' => 'الشركة الحديثة للتكنولوجيا', 'vat_number' => '300047061300003', 'cr_number' => null, 'street_name' => 'المدينة : الرياض الحي العليا', 'building_number' => null, 'district' => null, 'city' => 'الرياض', 'state' => null, 'postal_code' => '12222', 'email' => null, 'phone' => '966592441702', 'notes' => null],
            ['name' => 'الشركة الدولية لتوزيع المياة', 'vat_number' => '300134471900003', 'cr_number' => null, 'street_name' => 'شارع سعد الجنيدل', 'building_number' => null, 'district' => 'حي الروضة', 'city' => 'جدة', 'state' => null, 'postal_code' => '23435', 'email' => null, 'phone' => '126068008', 'notes' => null],
            ['name' => 'جامعة عفت', 'vat_number' => '300133667100003', 'cr_number' => null, 'street_name' => 'قصر حزام 8482حي النزلة اليمانية JINA8482', 'building_number' => null, 'district' => null, 'city' => 'جدة', 'state' => null, 'postal_code' => '22332', 'email' => 'rragban@effatuniversity.edu.sa', 'phone' => '+966 92 000 3331 Ext. 8051', 'notes' => null],
            ['name' => 'شركة ابداع الفريد للتطوير العقاري', 'vat_number' => '311758698500003', 'cr_number' => null, 'street_name' => 'حي العمرة شارع العثيم', 'building_number' => null, 'district' => null, 'city' => 'مكة المكرمة', 'state' => null, 'postal_code' => '24416', 'email' => null, 'phone' => null, 'notes' => null],
            ['name' => 'شركة الأعمال المدنية المحدودة', 'vat_number' => '300056375300003', 'cr_number' => null, 'street_name' => '7138 قرطبة - الراكه الجنوبيه الخبر 3247', 'building_number' => null, 'district' => null, 'city' => 'الخبر', 'state' => null, 'postal_code' => '34227', 'email' => null, 'phone' => 'PH: 8572205 FAX:8574733', 'notes' => null],
            ['name' => 'شركة الاعمال الذكية المحدودة شخص واحد', 'vat_number' => '311688273800003', 'cr_number' => null, 'street_name' => 'الامام عبدالله بن فيصل بن تركي', 'building_number' => '3983', 'district' => 'حي المربع', 'city' => 'الرياض', 'state' => null, 'postal_code' => '12613', 'email' => null, 'phone' => null, 'notes' => 'Plot ID: 7090'],
            ['name' => 'شركة الحلول الذكية المتقدمة المحدودة', 'vat_number' => '310590864100003', 'cr_number' => null, 'street_name' => null, 'building_number' => '28', 'district' => 'حي العليا', 'city' => 'الرياض', 'state' => null, 'postal_code' => '12211', 'email' => null, 'phone' => null, 'notes' => null],
            ['name' => 'شركة الخريف لتقنية المياة والطاقه', 'vat_number' => '300050372110003', 'cr_number' => null, 'street_name' => 'عليشة ،الرياض', 'building_number' => '6901', 'district' => 'عليشة', 'city' => 'الرياض', 'state' => null, 'postal_code' => '11595', 'email' => null, 'phone' => null, 'notes' => 'Plot ID: 2867'],
            ['name' => 'شركة الدايل للتجارة و الصناعةو المقاولات', 'vat_number' => '301338461600003', 'cr_number' => null, 'street_name' => 'جدة حي الاندلس الاميرمحمدبن عبدالعزيز', 'building_number' => null, 'district' => null, 'city' => 'جدة', 'state' => null, 'postal_code' => '23326', 'email' => null, 'phone' => null, 'notes' => null],
            ['name' => 'شركة الرواد للدواجن', 'vat_number' => '310638459300003', 'cr_number' => null, 'street_name' => '4912 القعرة', 'building_number' => null, 'district' => null, 'city' => 'القعرة', 'state' => null, 'postal_code' => '25390', 'email' => null, 'phone' => null, 'notes' => null],
            ['name' => 'شركة السعودية للنقل الجماعي', 'vat_number' => '300004441600003', 'cr_number' => null, 'street_name' => 'الرياض . حي النخيل شارع عمرو بن عبيد وحدة رقم 7995', 'building_number' => null, 'district' => null, 'city' => 'الرياض', 'state' => null, 'postal_code' => '22416', 'email' => null, 'phone' => null, 'notes' => null],
            ['name' => 'شركة الصمود الرائده للتجارة', 'vat_number' => '311398259100003', 'cr_number' => null, 'street_name' => 'شارع ابراهيم العنقري حي المحمديه', 'building_number' => null, 'district' => null, 'city' => 'جدة', 'state' => null, 'postal_code' => '23617', 'email' => null, 'phone' => null, 'notes' => null],
            ['name' => 'شركة العامورية', 'vat_number' => '310850486200003', 'cr_number' => null, 'street_name' => null, 'building_number' => '3985', 'district' => 'حي النسيم', 'city' => 'مكة المكرمة', 'state' => 'مكة المكرمة', 'postal_code' => '24245', 'email' => null, 'phone' => null, 'notes' => 'Plot ID: 7268'],
            ['name' => 'شركة العرض المتقن للخدمات التجارية', 'vat_number' => '300046479500003', 'cr_number' => null, 'street_name' => 'Al Shaikh Abdullah Ibn Jibrin', 'building_number' => '4077', 'district' => 'Al Qairawan Dist.', 'city' => null, 'state' => null, 'postal_code' => '13531', 'email' => null, 'phone' => null, 'notes' => 'Plot ID: 7537'],
            ['name' => 'شركة العيوني للإستثمار والمقاولات', 'vat_number' => '300055176400003', 'cr_number' => '1010066115', 'street_name' => 'شارع الثمامة تقاطع ابوبكر', 'building_number' => null, 'district' => 'حي الربيع', 'city' => 'الرياض', 'state' => null, 'postal_code' => null, 'email' => null, 'phone' => '966112405000', 'notes' => null],
            ['name' => 'شركة القسي العالمية', 'vat_number' => '300079757300003', 'cr_number' => null, 'street_name' => 'حي المعذر الشمالي', 'building_number' => null, 'district' => null, 'city' => 'جدة', 'state' => null, 'postal_code' => '21487', 'email' => null, 'phone' => '966557920075', 'notes' => null],
            ['name' => 'شركة المدائن العالية للمقاولات شركة شخص واحد', 'vat_number' => '311290154300003', 'cr_number' => null, 'street_name' => 'شارع البلدية', 'building_number' => '2543', 'district' => 'حي العزيزية', 'city' => 'جدة', 'state' => null, 'postal_code' => '23334', 'email' => null, 'phone' => null, 'notes' => 'Plot ID: 8335'],
            ['name' => 'شركة اليمامة للاعمال التجارية و المقاولات', 'vat_number' => '300436215600003', 'cr_number' => null, 'street_name' => 'ص ب 32223 رقم مبنى 7703', 'building_number' => '2150', 'district' => 'جدة', 'city' => 'جدة', 'state' => null, 'postal_code' => '32223', 'email' => null, 'phone' => '138266444', 'notes' => null],
            ['name' => 'شركة انذار للتشغيل والصيانه المحدوده', 'vat_number' => '300049623200003', 'cr_number' => null, 'street_name' => null, 'building_number' => null, 'district' => 'حي السليمانية', 'city' => 'الرياض', 'state' => null, 'postal_code' => '21434', 'email' => null, 'phone' => null, 'notes' => 'Additional street: طه حسين'],
            ['name' => 'شركة بركة الدولية المحدودة', 'vat_number' => '300262361200003', 'cr_number' => null, 'street_name' => 'حي الرويس شارع ام المومنين', 'building_number' => null, 'district' => null, 'city' => 'جدة', 'state' => null, 'postal_code' => '21465', 'email' => null, 'phone' => null, 'notes' => null],
            ['name' => 'شركة جلورك الدولية لتقديم الوجبات', 'vat_number' => '311365353800003', 'cr_number' => null, 'street_name' => 'جبل ابو مغير حي الصفا', 'building_number' => null, 'district' => null, 'city' => 'جدة', 'state' => null, 'postal_code' => '23451', 'email' => null, 'phone' => null, 'notes' => null],
            ['name' => 'شركة رؤية الطاقة المحدودة', 'vat_number' => '31113372800003', 'cr_number' => null, 'street_name' => 'المهندسين', 'building_number' => null, 'district' => 'حي الزيزي', 'city' => 'جدة', 'state' => null, 'postal_code' => '23334', 'email' => null, 'phone' => null, 'notes' => null],
            ['name' => 'شركة رضوى السعودية الغذائية المحدودة', 'vat_number' => '300281871800003', 'cr_number' => null, 'street_name' => 'طريق المدينه ذهبان', 'building_number' => null, 'district' => null, 'city' => 'جدة', 'state' => null, 'postal_code' => null, 'email' => null, 'phone' => null, 'notes' => null],
            ['name' => 'شركة سكويرروت للخدمات البحرية', 'vat_number' => '312251807200003', 'cr_number' => null, 'street_name' => 'طريق الملك عبدالله', 'building_number' => '6534', 'district' => 'حي الرويس', 'city' => 'جدة', 'state' => null, 'postal_code' => '23214', 'email' => null, 'phone' => null, 'notes' => 'Additional street: منازل المتقين | Plot ID: 4941'],
            ['name' => 'شركة صحارى لخدمات الصيانة المحدودة', 'vat_number' => '300054122600003', 'cr_number' => null, 'street_name' => 'شارع عمربن سليم', 'building_number' => '8509', 'district' => 'حي الريان', 'city' => 'الرياض', 'state' => null, 'postal_code' => '14212', 'email' => null, 'phone' => '4929884', 'notes' => 'Additional street: 3362'],
            ['name' => 'شركة صقر الجزيرة للصناعة و التجارة والمقاولات', 'vat_number' => '300055931300003', 'cr_number' => null, 'street_name' => 'مكة المكرمة طريق المدينة المنورة', 'building_number' => null, 'district' => null, 'city' => 'مكة المكرمة', 'state' => null, 'postal_code' => null, 'email' => null, 'phone' => null, 'notes' => null],
            ['name' => 'شركة طويق للصيانه و التشغيل المحدودة', 'vat_number' => '300056382100003', 'cr_number' => null, 'street_name' => null, 'building_number' => null, 'district' => 'المرسلات', 'city' => 'الرياض', 'state' => null, 'postal_code' => '11461', 'email' => null, 'phone' => '966530156823', 'notes' => null],
            ['name' => 'شركة فنون أشبيليا للصناعة', 'vat_number' => '310992194700003', 'cr_number' => null, 'street_name' => 'حي الصناعة طريق الملك فهد', 'building_number' => null, 'district' => null, 'city' => 'البدائع', 'state' => null, 'postal_code' => '56361', 'email' => null, 'phone' => null, 'notes' => null],
            ['name' => 'شركة محمد عبدالعزيز اللحيدان للتقنيه و الكهرباء المحدوده', 'vat_number' => '300102903300003', 'cr_number' => null, 'street_name' => null, 'building_number' => null, 'district' => null, 'city' => 'جدة', 'state' => null, 'postal_code' => '21514', 'email' => null, 'phone' => '966509784671', 'notes' => null],
            ['name' => 'شركة مخازن و الخدمات المساندة', 'vat_number' => '300082365200003', 'cr_number' => null, 'street_name' => 'Falcom Building 2nd floor P.O.Box 14650', 'building_number' => null, 'district' => null, 'city' => 'Al Riyadh', 'state' => null, 'postal_code' => '11434', 'email' => null, 'phone' => '+966 1200277', 'notes' => null],
            ['name' => 'شركة مصنع معدات نقل الطاقه و الاتصالات والمعدات الكهربائية', 'vat_number' => '300127581500003', 'cr_number' => null, 'street_name' => null, 'building_number' => '8131', 'district' => 'المدينة الصناعية الثالثة', 'city' => 'جدة', 'state' => 'Westrn Province', 'postal_code' => '22772', 'email' => 'info@pte-sa.com', 'phone' => '126095203', 'notes' => 'Plot ID: 2478'],
            ['name' => 'شركة مماس للخدمات اللوجستية', 'vat_number' => '310768160500003', 'cr_number' => null, 'street_name' => 'منازل المتقين 9167', 'building_number' => null, 'district' => null, 'city' => 'جدة', 'state' => null, 'postal_code' => '22312', 'email' => null, 'phone' => null, 'notes' => null],
            ['name' => 'شركة مناحى حمد مرعى الشهراني للمقاولات', 'vat_number' => '311175663700003', 'cr_number' => null, 'street_name' => 'حي المنتزه طريق الملك فهد', 'building_number' => null, 'district' => null, 'city' => 'خميس مشيط', 'state' => null, 'postal_code' => '62461', 'email' => null, 'phone' => null, 'notes' => null],
            ['name' => 'شركة نظم الانشاء و التعمير للمقاولات', 'vat_number' => '311312531400003', 'cr_number' => null, 'street_name' => 'الامير محمد بن عبدالعزيز الفرعي', 'building_number' => '2495', 'district' => 'حي العزيزة', 'city' => 'جدة', 'state' => null, 'postal_code' => '23334', 'email' => null, 'phone' => null, 'notes' => 'Additional street: 9020'],
            ['name' => 'مؤسسة السفيرية للتجارية', 'vat_number' => '300864093400003', 'cr_number' => null, 'street_name' => 'شارع الشيحان', 'building_number' => '5040', 'district' => 'حي الرحاب', 'city' => 'جدة', 'state' => null, 'postal_code' => '23344', 'email' => null, 'phone' => '012 5351607', 'notes' => null],
            ['name' => 'مؤسسة تكافل العامل للمقاولات', 'vat_number' => '301211198600003', 'cr_number' => null, 'street_name' => 'شارع العام مكة المكرمة', 'building_number' => null, 'district' => null, 'city' => 'مكة المكرمة', 'state' => null, 'postal_code' => '24222', 'email' => null, 'phone' => null, 'notes' => null],
            ['name' => 'مؤسسة علي بن سالم بن سعيد بقشان التجارية', 'vat_number' => '300449761500003', 'cr_number' => null, 'street_name' => null, 'building_number' => '7884', 'district' => 'حي المرسي', 'city' => 'جدة', 'state' => null, 'postal_code' => '22756', 'email' => null, 'phone' => null, 'notes' => 'Plot ID: 3809'],
            ['name' => 'مؤسسة عوض وصل الله فريح السلمي', 'vat_number' => '302137525200003', 'cr_number' => null, 'street_name' => 'شارع ا', 'building_number' => null, 'district' => 'حي الربوة', 'city' => 'جدة', 'state' => null, 'postal_code' => '21371', 'email' => null, 'phone' => null, 'notes' => null],
            ['name' => 'مؤسسة كنوز المدائن للمقاولات العامة', 'vat_number' => '301230985600003', 'cr_number' => null, 'street_name' => 'مكة المكرمه كدى الطريق الدائري الثالث', 'building_number' => null, 'district' => null, 'city' => 'مكة المكرمه كدى الطريق الدائري الثالث', 'state' => null, 'postal_code' => '24234', 'email' => null, 'phone' => null, 'notes' => null],
            ['name' => 'مؤسسة محمد العجيمي للمقاولات', 'vat_number' => '300456471800003', 'cr_number' => null, 'street_name' => 'الحي البدر 21499الدمام', 'building_number' => null, 'district' => null, 'city' => 'الدمام', 'state' => null, 'postal_code' => '21499', 'email' => null, 'phone' => '966505805342', 'notes' => null],
            ['name' => 'مؤسسةالمستوى العالي للمقاولات', 'vat_number' => '300823439900003', 'cr_number' => null, 'street_name' => 'جدة حي المروة شارع حراء', 'building_number' => null, 'district' => null, 'city' => 'جدة', 'state' => null, 'postal_code' => '23542', 'email' => null, 'phone' => '00966 549495548', 'notes' => null],
            ['name' => 'ورشة أبو عيد للخراطة', 'vat_number' => '300474011300003', 'cr_number' => null, 'street_name' => 'شارع 20 ص ب 710 المنطقة الصناعية تبوك', 'building_number' => null, 'district' => null, 'city' => 'تبوك', 'state' => null, 'postal_code' => null, 'email' => null, 'phone' => null, 'notes' => null],
            ['name' => 'ورشة جميل', 'vat_number' => null, 'cr_number' => null, 'street_name' => null, 'building_number' => null, 'district' => null, 'city' => 'مكة', 'state' => null, 'postal_code' => null, 'email' => null, 'phone' => null, 'notes' => null],
        ];

        foreach ($clients as $data) {
            Client::updateOrCreate(
                ['company_id' => $company->id, 'name' => $data['name']],
                $data + [
                    'company_id' => $company->id,
                    'type' => 'company',
                    'country' => 'Saudi Arabia',
                    'is_vat_registered' => ! empty($data['vat_number']),
                ]
            );
        }

        $this->command?->info(count($clients).' Zubaidi clients seeded.');
    }
}

