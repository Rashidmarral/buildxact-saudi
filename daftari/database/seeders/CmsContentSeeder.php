<?php

namespace Database\Seeders;

use App\Models\CmsSection;
use Illuminate\Database\Seeder;

/**
 * Seeds the exact copy that used to be hardcoded in resources/views/site/*
 * as admin-editable CmsSection/CmsSectionItem rows, so a fresh install (or
 * an existing install running this migration for the first time) shows the
 * same marketing pages as before — just now editable from Admin → Website
 * CMS instead of requiring a code change. Idempotent: skips a page that
 * already has sections, so re-running `db:seed` never duplicates content an
 * admin has since edited.
 */
class CmsContentSeeder extends Seeder
{
    /**
     * about/contact are excluded from the coarse "page already has content"
     * skip below: two data migrations (expand_cms_content_and_add_global_
     * blocks, add_payroll_pos_and_expand_marketing_content) pre-create a
     * handful of rows for exactly those two pages before this seeder ever
     * runs on a fresh install, so the generic check would see "this page
     * already has a section" and skip seeding the rest of it entirely —
     * no hero, no pillars, no contact methods. seedAbout()/seedContact()
     * are themselves fully idempotent per-section instead, so it's safe
     * to always call them.
     */
    private const ALWAYS_RESEED_PAGES = ['about', 'contact'];

    public function run(): void
    {
        foreach (CmsSection::PAGES as $page) {
            if (! in_array($page, self::ALWAYS_RESEED_PAGES, true) && CmsSection::query()->where('page', $page)->exists()) {
                continue;
            }

            $this->{'seed'.ucfirst($page)}();
        }
    }

    private function seedHome(): void
    {
        $this->section('home', 'hero', 1, [
            'badge_en' => 'ZATCA-ready e-invoicing',
            'badge_ar' => 'فوترة إلكترونية متوافقة مع متطلبات هيئة الزكاة والضريبة والجمارك',
            'title_en' => 'VAT invoicing & accounting, built for Saudi businesses',
            'title_ar' => 'فوترة ضريبة القيمة المضافة والمحاسبة، مصممة للشركات السعودية',
            'subtitle_en' => 'Daftari is a subscription-based accounting platform for Saudi companies — create compliant VAT invoices with QR codes, track expenses, manage clients, and see your VAT position at a glance. Bilingual Arabic/English, priced in SAR.',
            'subtitle_ar' => 'دفتري منصة محاسبة بنظام الاشتراك للشركات السعودية — أنشئ فواتير ضريبية متوافقة مع رمز QR، وتتبّع المصروفات، وأدر عملاءك، واطّلع على وضعك الضريبي بلمحة واحدة. ثنائية اللغة عربي/إنجليزي، وبأسعار بالريال السعودي.',
            'link_text_en' => 'Start your free trial',
            'link_text_ar' => 'ابدأ تجربتك المجانية',
        ]);

        $trust = $this->section('home', 'stats', 2);
        $this->item($trust, 1, ['title_en' => '15%', 'title_ar' => '15%', 'subtitle_en' => 'VAT rate', 'subtitle_ar' => 'نسبة الضريبة']);
        $this->item($trust, 2, ['title_en' => 'On every invoice', 'title_ar' => 'في كل فاتورة', 'subtitle_en' => 'QR code', 'subtitle_ar' => 'رمز QR']);
        $this->item($trust, 3, ['title_en' => 'Arabic & English', 'title_ar' => 'العربية والإنجليزية', 'subtitle_en' => 'Languages', 'subtitle_ar' => 'اللغات']);
        $this->item($trust, 4, ['title_en' => 'SAR', 'title_ar' => 'ريال سعودي', 'subtitle_en' => 'Currency', 'subtitle_ar' => 'العملة']);

        $features = $this->section('home', 'feature_grid', 3, [
            'title_en' => 'Everything you need to run compliant, organized books',
            'title_ar' => 'كل ما تحتاجه لإدارة سجلات محاسبية منظمة ومتوافقة مع الأنظمة',
        ]);
        $featureRows = [
            ['🧾', 'Create and send invoices', 'إنشاء الفواتير وإرسالها', 'Build VAT-compliant invoices with line items and send them to clients, tracked by status.', 'أنشئ فواتير متوافقة مع ضريبة القيمة المضافة تتضمن بنودًا تفصيلية، وأرسلها لعملائك، مع تتبع حالتها.'],
            ['🏷️', 'Apply tax rates', 'تطبيق نسب الضريبة', 'Set a default VAT rate per item and override it per invoice line when needed.', 'حدد نسبة ضريبة افتراضية لكل عنصر، وعدّلها لكل بند في الفاتورة عند الحاجة.'],
            ['📱', 'ZATCA Phase 1 & Phase 2 e-invoicing', 'الفوترة الإلكترونية للمرحلتين الأولى والثانية من هيئة الزكاة والضريبة والجمارك', "QR codes, XML generation, XAdES digital signing, and real-time clearance/reporting with ZATCA's API, built in.", 'رموز QR وإنشاء XML والتوقيع الرقمي XAdES والتخليص/الإبلاغ اللحظي مع واجهة برمجة تطبيقات هيئة الزكاة والضريبة والجمارك، مدمجة بالكامل.'],
            ['💳', 'Expense tracking', 'تتبع المصروفات', 'Record and categorize purchases and their recoverable VAT so nothing falls through the cracks.', 'سجّل مشترياتك وصنّفها مع ضريبة القيمة المضافة القابلة للاسترداد حتى لا يفوتك شيء.'],
            ['📈', 'VAT return report', 'تقرير إقرار ضريبة القيمة المضافة', 'A summary of output VAT, input VAT, and net VAT due for any period, ready to review before filing.', 'ملخص لضريبة المخرجات، وضريبة المدخلات، وصافي الضريبة المستحقة لأي فترة، جاهز للمراجعة قبل التقديم.'],
            ['🧑\u{200D}🤝\u{200D}🧑', 'Users, custom roles & permissions', 'المستخدمون والأدوار والصلاحيات المخصصة', 'Invite your team and assign system or custom roles with fine-grained permissions per module.', 'ادعُ فريقك وحدد أدوارًا نظامية أو مخصصة بصلاحيات دقيقة لكل وحدة.'],
            ['💰', 'Cash, bank accounts & vouchers', 'الحسابات النقدية والبنكية والسندات', 'Track multiple bank and cash accounts, receipt/payment vouchers, and transfers between them.', 'تتبّع حسابات بنكية ونقدية متعددة وسندات القبض/الصرف والتحويلات بينها.'],
            ['📄', 'Supplier bills', 'فواتير الموردين', 'Organize purchases and costs from your suppliers for accurate reporting.', 'نظّم المشتريات والتكاليف من مورديك لتقارير دقيقة.'],
            ['📝', 'Purchase orders', 'أوامر الشراء', 'Document purchase requests before they become costs, for clearer purchasing visibility.', 'وثّق طلبات الشراء قبل أن تتحول إلى تكاليف، لرؤية أوضح لعمليات الشراء.'],
            ['↩️', 'Credit & debit notes', 'إشعارات الدائن والمدين', 'Record sales returns and purchase adjustments as proper ZATCA-compliant notes.', 'سجّل مرتجعات المبيعات وتسويات المشتريات كإشعارات متوافقة مع هيئة الزكاة والضريبة والجمارك.'],
            ['💬', 'Quotations & proforma invoices', 'عروض الأسعار والفواتير الأولية', 'Send a professional quote and convert it straight into an invoice once approved.', 'أرسل عرض سعر احترافيًا وحوّله مباشرة إلى فاتورة بعد الموافقة عليه.'],
            ['🔁', 'Recurring invoices', 'الفواتير المتكررة', 'Set up a billing schedule once and a draft invoice is generated automatically each time it runs.', 'أعدّ جدول الفوترة مرة واحدة، وسيتم إنشاء فاتورة مسودة تلقائيًا في كل مرة يعمل فيها.'],
            ['📊', 'Cash flow, trial balance & financial statements', 'التدفق النقدي وميزان المراجعة والقوائم المالية', 'Balance sheet, income statement, cash flow, trial balance, and account statement reports.', 'الميزانية العمومية وقائمة الدخل والتدفق النقدي وميزان المراجعة وكشوف الحسابات.'],
            ['🗂️', 'Cost centers', 'مراكز التكلفة', 'Allocate costs to departments, branches, or activities for clearer breakdowns.', 'وزّع التكاليف على الأقسام أو الفروع أو الأنشطة لتفصيل أوضح.'],
            ['📦', 'Inventory, units & warehouses', 'المخزون والوحدات والمستودعات', 'Track stock across warehouses, sell in alternate units with automatic conversion, and adjust stock.', 'تتبّع المخزون عبر المستودعات، وبِع بوحدات بديلة مع التحويل التلقائي، وعدّل المخزون.'],
            ['🏬', 'Branches', 'الفروع', 'Organize invoices, bills, and expenses by branch and track performance separately.', 'نظّم الفواتير والمصروفات حسب الفرع وتتبع أداء كل فرع على حدة.'],
            ['🎨', 'Invoice & document templates', 'قوالب الفواتير والمستندات', 'Multiple document templates and layouts, with your logo, stamp, and letterhead, that unify the look of your paperwork.', 'قوالب وتخطيطات متعددة للمستندات، بشعارك وختمك وترويستك، توحّد شكل مستنداتك.'],
            ['🌍', 'Bilingual, RTL-ready', 'ثنائي اللغة، وجاهز للعرض من اليمين لليسار', 'Full Arabic and English interface with proper right-to-left layout.', 'واجهة كاملة بالعربية والإنجليزية مع تخطيط صحيح من اليمين إلى اليسار.'],
            ['🔔', 'In-app notifications & 2FA', 'الإشعارات داخل التطبيق والتحقق بخطوتين', 'Stay on top of what needs attention, and secure your account with two-factor authentication.', 'تابع كل ما يحتاج انتباهك، وأمّن حسابك بالتحقق بخطوتين.'],
            ['🧮', 'Full payroll, GOSI & WPS', 'رواتب كاملة والتأمينات ونظام حماية الأجور', 'Employee records, monthly payroll runs with GOSI employee/employer contributions, WPS bank-file export, payslips, and end-of-service gratuity — all posted to your books automatically.', 'سجلات الموظفين ودورات رواتب شهرية مع اشتراكات التأمينات الاجتماعية للموظف وصاحب العمل، وملف بنكي لنظام حماية الأجور، وقسائم رواتب، ومكافأة نهاية الخدمة — تُرحّل جميعها تلقائيًا إلى سجلاتك المحاسبية.'],
            ['🛒', 'Point of sale (POS)', 'نقطة البيع', 'A touch-friendly checkout for retail counters — barcode scan, split cash/card payments, register shifts with a Z-report reconciliation, and a ZATCA-compliant QR receipt on every sale.', 'شاشة دفع سريعة الاستخدام لمنافذ البيع بالتجزئة — مسح الباركود، ودفع مقسّم نقدًا وبالبطاقة، وورديات صندوق مع تسوية تقرير Z، وإيصال بيع يتضمن رمز QR متوافقًا مع هيئة الزكاة والضريبة والجمارك.'],
        ];
        foreach ($featureRows as $i => [$icon, $tEn, $tAr, $bEn, $bAr]) {
            $this->item($features, $i + 1, ['icon' => $icon, 'title_en' => $tEn, 'title_ar' => $tAr, 'body_en' => $bEn, 'body_ar' => $bAr]);
        }

        $this->section('home', 'cta', 4, [
            'title_en' => 'Ready to simplify your VAT invoicing?',
            'title_ar' => 'هل أنت مستعد لتبسيط فوترتك الضريبية؟',
            'subtitle_en' => 'Join Saudi businesses managing their invoicing and VAT with Daftari.',
            'subtitle_ar' => 'انضم إلى الشركات السعودية التي تدير فواتيرها وضريبتها عبر دفتري.',
            'link_text_en' => 'Start your free trial',
            'link_text_ar' => 'ابدأ تجربتك المجانية',
        ]);
    }

    private function seedFeatures(): void
    {
        $this->section('features', 'hero', 1, [
            'title_en' => 'Features — everything for VAT invoicing, in one place',
            'title_ar' => 'المزايا — كل ما يخص فوترة ضريبة القيمة المضافة، في مكان واحد',
            'subtitle_en' => 'Daftari brings invoicing, expenses, purchasing, inventory, accounting, payroll, point of sale, and VAT reporting together for Saudi businesses — one connected platform instead of a patchwork of tools. Everything below is live today.',
            'subtitle_ar' => 'يجمع دفتري بين الفوترة والمصروفات والمشتريات والمخزون والمحاسبة والرواتب ونقطة البيع وتقارير ضريبة القيمة المضافة في منصة واحدة متكاملة للأعمال السعودية، بدلًا من أدوات متفرقة. كل ما يظهر أدناه متاح اليوم بالكامل.',
        ]);

        $grid = $this->section('features', 'feature_grid', 2);
        $rows = [
            ['🧾', 'Create and send invoices', 'إنشاء الفواتير وإرسالها', 'Build VAT-compliant invoices with line items and send them to clients, tracked by status.', 'أنشئ فواتير متوافقة مع ضريبة القيمة المضافة تتضمن بنودًا تفصيلية، وأرسلها لعملائك، مع تتبع حالتها.'],
            ['🏷️', 'Apply tax rates', 'تطبيق نسب الضريبة', 'Set a default VAT rate per item and override it per invoice line when needed.', 'حدد نسبة ضريبة افتراضية لكل عنصر، وعدّلها لكل بند في الفاتورة عند الحاجة.'],
            ['📱', 'ZATCA Phase 1 & Phase 2 e-invoicing', 'الفوترة الإلكترونية للمرحلتين الأولى والثانية من هيئة الزكاة والضريبة والجمارك', "QR codes, XML generation, XAdES digital signing, and real-time clearance/reporting with ZATCA's API, built in.", 'رموز QR وإنشاء XML والتوقيع الرقمي XAdES والتخليص/الإبلاغ اللحظي مع واجهة برمجة تطبيقات هيئة الزكاة والضريبة والجمارك، مدمجة بالكامل.'],
            ['💳', 'Expense tracking', 'تتبع المصروفات', 'Record and categorize purchases and their recoverable VAT so nothing falls through the cracks.', 'سجّل مشترياتك وصنّفها مع ضريبة القيمة المضافة القابلة للاسترداد حتى لا يفوتك شيء.'],
            ['📈', 'VAT return report', 'تقرير إقرار ضريبة القيمة المضافة', 'A summary of output VAT, input VAT, and net VAT due for any period, ready to review before filing.', 'ملخص لضريبة المخرجات، وضريبة المدخلات، وصافي الضريبة المستحقة لأي فترة، جاهز للمراجعة قبل التقديم.'],
            ['🧑\u{200D}🤝\u{200D}🧑', 'Users, custom roles & permissions', 'المستخدمون والأدوار والصلاحيات المخصصة', 'Invite your team and assign system or custom roles with fine-grained permissions per module.', 'ادعُ فريقك وحدد أدوارًا نظامية أو مخصصة بصلاحيات دقيقة لكل وحدة.'],
            ['💰', 'Cash, bank accounts & vouchers', 'الحسابات النقدية والبنكية والسندات', 'Track multiple bank and cash accounts, receipt/payment vouchers, and transfers between them.', 'تتبّع حسابات بنكية ونقدية متعددة وسندات القبض/الصرف والتحويلات بينها.'],
            ['📄', 'Supplier bills', 'فواتير الموردين', 'Organize purchases and costs from your suppliers for accurate reporting.', 'نظّم المشتريات والتكاليف من مورديك لتقارير دقيقة.'],
            ['📝', 'Purchase orders', 'أوامر الشراء', 'Document purchase requests before they become costs, for clearer purchasing visibility.', 'وثّق طلبات الشراء قبل أن تتحول إلى تكاليف، لرؤية أوضح لعمليات الشراء.'],
            ['↩️', 'Credit & debit notes', 'إشعارات الدائن والمدين', 'Record sales returns and purchase adjustments as proper ZATCA-compliant notes.', 'سجّل مرتجعات المبيعات وتسويات المشتريات كإشعارات متوافقة مع هيئة الزكاة والضريبة والجمارك.'],
            ['💬', 'Quotations & proforma invoices', 'عروض الأسعار والفواتير الأولية', 'Send a professional quote and convert it straight into an invoice once approved.', 'أرسل عرض سعر احترافيًا وحوّله مباشرة إلى فاتورة بعد الموافقة عليه.'],
            ['🔁', 'Recurring invoices', 'الفواتير المتكررة', 'Set up a billing schedule once and a draft invoice is generated automatically each time it runs.', 'أعدّ جدول الفوترة مرة واحدة، وسيتم إنشاء فاتورة مسودة تلقائيًا في كل مرة يعمل فيها.'],
            ['📊', 'Cash flow, trial balance & financial statements', 'التدفق النقدي وميزان المراجعة والقوائم المالية', 'Balance sheet, income statement, cash flow, trial balance, and account statement reports.', 'الميزانية العمومية وقائمة الدخل والتدفق النقدي وميزان المراجعة وكشوف الحسابات.'],
            ['🗂️', 'Cost centers', 'مراكز التكلفة', 'Allocate costs to departments, branches, or activities for clearer breakdowns.', 'وزّع التكاليف على الأقسام أو الفروع أو الأنشطة لتفصيل أوضح.'],
            ['📦', 'Inventory, units & warehouses', 'المخزون والوحدات والمستودعات', 'Track stock across warehouses, sell in alternate units with automatic conversion, and adjust stock.', 'تتبّع المخزون عبر المستودعات، وبِع بوحدات بديلة مع التحويل التلقائي، وعدّل المخزون.'],
            ['🏬', 'Branches', 'الفروع', 'Organize invoices, bills, and expenses by branch and track performance separately.', 'نظّم الفواتير والمصروفات حسب الفرع وتتبع أداء كل فرع على حدة.'],
            ['🎨', 'Invoice & document templates', 'قوالب الفواتير والمستندات', 'Multiple document templates and layouts, with your logo, stamp, and letterhead, that unify the look of your paperwork.', 'قوالب وتخطيطات متعددة للمستندات، بشعارك وختمك وترويستك، توحّد شكل مستنداتك.'],
            ['🏦', 'Account reconciliation', 'تسوية الحسابات', 'Match bank and cash activity against your records so your numbers reflect reality.', 'طابق الحركة البنكية والنقدية مع سجلاتك حتى تعكس أرقامك واقع منشأتك.'],
            ['🌍', 'Multi-currency', 'تعدد العملات', 'Work in more than one currency and track exchange rates clearly.', 'اعمل بأكثر من عملة وتتبع أسعار الصرف بوضوح.'],
            ['🧮', 'Full payroll, GOSI & WPS', 'رواتب كاملة والتأمينات ونظام حماية الأجور', 'Employee records, monthly payroll runs with GOSI employee/employer contributions, WPS bank-file export, payslips, and end-of-service gratuity — all posted to your books automatically.', 'سجلات الموظفين ودورات رواتب شهرية مع اشتراكات التأمينات الاجتماعية للموظف وصاحب العمل، وملف بنكي لنظام حماية الأجور، وقسائم رواتب، ومكافأة نهاية الخدمة — تُرحّل جميعها تلقائيًا إلى سجلاتك المحاسبية.'],
            ['🛒', 'Point of sale (POS)', 'نقطة البيع', 'A touch-friendly checkout for retail counters — barcode scan, split cash/card payments, register shifts with a Z-report reconciliation, and a ZATCA-compliant QR receipt on every sale.', 'شاشة دفع سريعة الاستخدام لمنافذ البيع بالتجزئة — مسح الباركود، ودفع مقسّم نقدًا وبالبطاقة، وورديات صندوق مع تسوية تقرير Z، وإيصال بيع يتضمن رمز QR متوافقًا مع هيئة الزكاة والضريبة والجمارك.'],
        ];
        foreach ($rows as $i => [$icon, $tEn, $tAr, $bEn, $bAr]) {
            $this->item($grid, $i + 1, ['icon' => $icon, 'title_en' => $tEn, 'title_ar' => $tAr, 'body_en' => $bEn, 'body_ar' => $bAr]);
        }

        $this->section('features', 'cta', 3, [
            'title_en' => 'Ready to simplify your VAT invoicing?',
            'title_ar' => 'هل أنت مستعد لتبسيط فوترتك الضريبية؟',
            'subtitle_en' => 'Join Saudi businesses managing their invoicing and VAT with Daftari.',
            'subtitle_ar' => 'انضم إلى الشركات السعودية التي تدير فواتيرها وضريبتها عبر دفتري.',
            'link_text_en' => 'Start your free trial',
            'link_text_ar' => 'ابدأ تجربتك المجانية',
        ]);
    }

    private function seedPricing(): void
    {
        $this->section('pricing', 'hero', 1, [
            'title_en' => 'Simple, transparent pricing',
            'title_ar' => 'تسعير بسيط وشفاف',
        ]);
    }

    /**
     * Fully idempotent per-section (unlike most other seed*() methods in
     * this class, which assume they only ever run once on a page with
     * zero content) — see ALWAYS_RESEED_PAGES above. Two earlier data
     * migrations pre-create a couple of rows for the about/contact pages
     * before this seeder ever runs on a fresh install, so every section
     * here is only created if a matching one doesn't already exist. This
     * converges to the same end state whether the page is completely
     * empty, partially seeded by a migration, or already fully seeded.
     */
    private function seedAbout(): void
    {
        $hero = CmsSection::query()->where('page', 'about')->where('title_en', 'About Daftari')->first()
            ?? $this->section('about', 'text', 1, [
                'title_en' => 'About Daftari',
                'title_ar' => 'عن دفتري',
                'body_en' => "Daftari was built for one reason: Saudi businesses deserve accounting software that speaks their language — literally and in terms of VAT compliance, SAR pricing, and local business practices.\n\nWe focus on the everyday workflow of a growing business: quote a client, invoice them with the correct VAT and a compliant QR code, track what is owed, log your expenses, and know your VAT position before the return is due — without hiring an accountant just to keep the books straight.",
                'body_ar' => "بُني دفتري لسبب واحد: الشركات السعودية تستحق برنامج محاسبة يتحدث لغتها — حرفيًا ومن حيث الامتثال الضريبي، والتسعير بالريال، وممارسات الأعمال المحلية.\n\nنركّز على سير العمل اليومي للأعمال النامية: قدّم عرض سعر لعميلك، أصدر له فاتورة بالضريبة الصحيحة ورمز QR متوافق، وتتبّع المستحقات، وسجّل مصروفاتك، واعرف وضعك الضريبي قبل موعد الإقرار — دون الحاجة لتوظيف محاسب فقط لتنظيم سجلاتك.",
            ]);

        $pillars = CmsSection::query()->where('page', 'about')->where('type', 'feature_grid')->first()
            ?? $this->section('about', 'feature_grid', 2);
        $pillarRows = [
            ['Compliance first', 'الامتثال أولًا', 'Every invoice includes standards-based VAT calculation and a scannable QR code.', 'تتضمن كل فاتورة احتسابًا للضريبة وفق المعايير المعتمدة ورمز QR قابل للمسح.'],
            ['Built for Arabic & English', 'مصمم للعربية والإنجليزية', 'A genuinely bilingual product with right-to-left layout, not a translated afterthought.', 'منتج ثنائي اللغة بالفعل، مصمم بتخطيط من اليمين إلى اليسار، وليس ترجمة لاحقة.'],
            ['Fair, transparent pricing', 'أسعار عادلة وشفافة', 'Simple SAR pricing with no hidden fees — upgrade, downgrade, or cancel anytime.', 'تسعير بسيط بالريال السعودي بدون رسوم خفية — رقّي باقتك أو خفّضها أو ألغِ اشتراكك في أي وقت.'],
        ];
        foreach ($pillarRows as $i => [$tEn, $tAr, $bEn, $bAr]) {
            if ($pillars->items()->where('title_en', $tEn)->exists()) {
                continue;
            }
            $this->item($pillars, $i + 1, ['title_en' => $tEn, 'title_ar' => $tAr, 'body_en' => $bEn, 'body_ar' => $bAr]);
        }

        $howWeBuild = CmsSection::query()->where('page', 'about')->where('title_en', 'How we build Daftari')->first()
            ?? $this->section('about', 'text', 3, [
                'title_en' => 'How we build Daftari',
                'title_ar' => 'كيف نبني دفتري',
                'body_en' => "We ship the modules a growing Saudi business actually needs — invoicing, purchasing, inventory, accounting, payroll, point of sale, and ZATCA compliance — as one connected product instead of bolted-together add-ons.\n\nSupport comes from people who understand Saudi VAT and e-invoicing, not a generic help desk, so when a question is specific to your business, you get a specific answer.",
                'body_ar' => "نبني الوحدات التي تحتاجها فعليًا منشأة سعودية نامية — الفوترة والمشتريات والمخزون والمحاسبة والرواتب ونقطة البيع والامتثال لهيئة الزكاة والضريبة والجمارك — كمنتج واحد متكامل بدلًا من إضافات منفصلة.\n\nيأتي الدعم من أشخاص يفهمون ضريبة القيمة المضافة والفوترة الإلكترونية في السعودية، لا مركز دعم عام، فحين يكون سؤالك خاصًا بمنشأتك، تحصل على إجابة محددة.",
                'image_position' => 'right',
            ]);

        $stats = CmsSection::query()->where('page', 'about')->where('type', 'stats')->first()
            ?? $this->section('about', 'stats', 4, [
                'title_en' => 'One platform, every module',
                'title_ar' => 'منصة واحدة، بكل الوحدات',
            ]);
        $statRows = [
            ['10+', '10+', 'Connected modules', 'وحدات مترابطة'],
            ['Phase 1 & 2', 'المرحلتان 1 و2', 'ZATCA e-invoicing', 'الفوترة الإلكترونية لهيئة الزكاة'],
            ['GOSI & WPS', 'التأمينات ونظام الأجور', 'Compliant payroll', 'رواتب متوافقة مع الأنظمة'],
            ['AR / EN', 'عربي / إنجليزي', 'Fully bilingual, RTL-ready', 'ثنائي اللغة بالكامل وجاهز لليمين-يسار'],
        ];
        foreach ($statRows as $i => [$tEn, $tAr, $sEn, $sAr]) {
            if ($stats->items()->where('title_en', $tEn)->exists()) {
                continue;
            }
            $this->item($stats, $i + 1, ['title_en' => $tEn, 'title_ar' => $tAr, 'subtitle_en' => $sEn, 'subtitle_ar' => $sAr]);
        }

        $testimonials = CmsSection::query()->where('page', 'about')->where('type', 'testimonials')->first()
            ?? $this->section('about', 'testimonials', 5, [
                'title_en' => 'What business owners say',
                'title_ar' => 'ماذا يقول أصحاب الأعمال',
            ]);
        $testimonialRows = [
            ['We moved our invoicing, purchasing, and VAT reporting into Daftari in an afternoon. Having payroll and POS in the same place now means our accountant only looks in one place at month end.', 'نقلنا فوترتنا ومشترياتنا وتقارير ضريبة القيمة المضافة إلى دفتري في يوم واحد. وجود الرواتب ونقطة البيع في نفس المكان الآن يعني أن محاسبنا ينظر في مكان واحد فقط في نهاية الشهر.', 'Finance Manager', 'مدير مالي', 'Contracting business', 'شركة مقاولات'],
            ['The GOSI and WPS calculations used to take our bookkeeper a full day every month. Now the payroll run does it in minutes and posts straight to the books.', 'كانت حسابات التأمينات ونظام حماية الأجور تستغرق من محاسبنا يومًا كاملًا كل شهر. الآن تتم دورة الرواتب خلال دقائق وتُرحّل مباشرة إلى السجلات.', 'Owner', 'صاحب المنشأة', 'Retail chain', 'سلسلة متاجر تجزئة'],
            ['Our cashiers picked up the POS screen in minutes, and every receipt already carries a proper ZATCA QR code — one less thing to worry about.', 'تعلّم أمناء الصندوق لدينا استخدام شاشة نقطة البيع خلال دقائق، وكل إيصال يحمل رمز QR متوافقًا مع هيئة الزكاة والضريبة — أمر أقل نقلقه.', 'Operations Lead', 'مسؤول العمليات', 'Food & beverage outlet', 'منفذ أغذية ومشروبات'],
        ];
        foreach ($testimonialRows as $i => [$bEn, $bAr, $tEn, $tAr, $sEn, $sAr]) {
            if ($testimonials->items()->where('title_en', $tEn)->exists()) {
                continue;
            }
            $this->item($testimonials, $i + 1, ['title_en' => $tEn, 'title_ar' => $tAr, 'subtitle_en' => $sEn, 'subtitle_ar' => $sAr, 'body_en' => $bEn, 'body_ar' => $bAr]);
        }

        // Sections above are found-or-created rather than always freshly
        // inserted, so two of them can come from a data migration that ran
        // before this seeder with its own sort_order — pin the final
        // display order explicitly rather than trusting insertion order.
        foreach ([$hero, $pillars, $howWeBuild, $stats, $testimonials] as $order => $section) {
            $section->update(['sort_order' => $order + 1]);
        }
    }

    private function seedCompliance(): void
    {
        $this->section('compliance', 'hero', 1, [
            'title_en' => 'Saudi e-invoicing & tax compliance, explained simply',
            'title_ar' => 'الفوترة الإلكترونية والامتثال الضريبي في السعودية، بشرح مبسط',
            'subtitle_en' => 'A practical guide to what ZATCA e-invoicing, VAT, and record-keeping actually require from your business — in plain language, so you know what applies to you before you need it.',
            'subtitle_ar' => 'دليل عملي لما تتطلبه فعليًا الفوترة الإلكترونية لهيئة الزكاة والضريبة والجمارك، وضريبة القيمة المضافة، وحفظ السجلات من منشأتك — بلغة مبسطة، لتعرف ما ينطبق عليك قبل أن تحتاجه.',
        ]);

        $topics = $this->section('compliance', 'feature_grid', 2, [
            'title_en' => 'Choose a topic',
            'title_ar' => 'اختر موضوعًا',
        ]);
        $rows = [
            ['Phase 1 — Generation', 'المرحلة الأولى — الإصدار', 'What a compliant electronic invoice must include, and how it must be generated and stored from day one.', 'ما يجب أن تتضمنه الفاتورة الإلكترونية المتوافقة، وكيف يجب إصدارها وحفظها منذ اليوم الأول.'],
            ['Phase 2 — Integration', 'المرحلة الثانية — الربط', 'When ZATCA integration becomes mandatory for a business, and what changes in practice once it applies to you.', 'متى يصبح الربط مع هيئة الزكاة والضريبة والجمارك إلزاميًا للمنشأة، وما الذي يتغير عمليًا بمجرد انطباقه عليك.'],
            ['Rollout waves', 'موجات التطبيق', 'How ZATCA phases businesses in by revenue threshold, and how to find which wave and deadline applies to you.', 'كيف تُدرج هيئة الزكاة والضريبة والجمارك المنشآت على مراحل وفق حد الإيرادات، وكيف تعرف الموجة والموعد النهائي الذي ينطبق عليك.'],
            ['Record keeping', 'حفظ السجلات', 'What invoicing and accounting records to retain, for how long, and how to stay ready for an audit.', 'ما هي سجلات الفوترة والمحاسبة الواجب الاحتفاظ بها، ولأي مدة، وكيف تبقى جاهزًا لأي تدقيق.'],
            ['VAT basics', 'أساسيات ضريبة القيمة المضافة', 'The fundamentals of VAT registration, standard rates, and preparing your figures ahead of filing.', 'أساسيات التسجيل في ضريبة القيمة المضافة، والنسب المعتمدة، وتجهيز أرقامك قبل التقديم.'],
            ['Withholding tax', 'ضريبة الاستقطاع', 'A plain-language overview of when withholding tax can apply and how it is typically handled.', 'نظرة مبسطة توضح متى يمكن أن تنطبق ضريبة الاستقطاع وكيف يتم التعامل معها عادةً.'],
            ['WPS', 'نظام حماية الأجور', 'A simplified overview of the Wage Protection System and what employers are generally expected to do.', 'نظرة مبسطة على نظام حماية الأجور وما يُتوقع من أصحاب العمل القيام به عمومًا.'],
            ['GOSI', 'التأمينات الاجتماعية (جوسي)', 'The essentials of employer registration and contributions through official channels.', 'أساسيات تسجيل صاحب العمل والاشتراكات عبر القنوات الرسمية.'],
        ];
        foreach ($rows as $i => [$tEn, $tAr, $bEn, $bAr]) {
            $this->item($topics, $i + 1, ['title_en' => $tEn, 'title_ar' => $tAr, 'body_en' => $bEn, 'body_ar' => $bAr]);
        }

        $faq = $this->section('compliance', 'faq', 3, [
            'title_en' => 'Frequently asked questions',
            'title_ar' => 'الأسئلة الشائعة',
        ]);
        $qa = [
            ['Is Daftari compliant with ZATCA e-invoicing requirements?', 'هل دفتري متوافق مع متطلبات الفوترة الإلكترونية لهيئة الزكاة والضريبة والجمارك؟', "Yes. Every tax invoice includes standards-based VAT calculation and a scannable QR code out of the box, and full Phase 2 integration — XML generation, XAdES digital signing, and real-time clearance/reporting with ZATCA's API — is built in. Connect your ZATCA credentials from the ZATCA section of your dashboard to turn it on.", 'نعم. تتضمن كل فاتورة ضريبية حساب ضريبة القيمة المضافة وفق المعايير ورمز QR قابل للمسح من الصندوق مباشرة، كما أن تكامل المرحلة الثانية بالكامل — إنشاء XML والتوقيع الرقمي XAdES والتخليص/الإبلاغ اللحظي مع واجهة برمجة تطبيقات هيئة الزكاة — مدمج بالفعل. اربط بيانات اعتماد هيئة الزكاة من قسم ZATCA في لوحة التحكم لتفعيله.'],
            ['Is Phase 2 mandatory for my business right now?', 'هل المرحلة الثانية إلزامية لمنشأتي الآن؟', 'ZATCA rolls Phase 2 out in waves by revenue threshold, so it depends on your business. Check the official ZATCA announcements for your specific wave and deadline.', 'تطرح هيئة الزكاة والضريبة والجمارك المرحلة الثانية على موجات حسب حد الإيرادات، لذا يعتمد الأمر على منشأتك. راجع الإعلانات الرسمية للهيئة لمعرفة موجتك وموعدها النهائي.'],
            ['Do I need accounting experience to use Daftari?', 'هل أحتاج إلى خبرة محاسبية لاستخدام دفتري؟', 'No. Daftari is built so a business owner can issue a compliant invoice, log an expense, and read their VAT position without prior bookkeeping experience.', 'لا. صُمم دفتري ليتمكن صاحب المنشأة من إصدار فاتورة متوافقة، وتسجيل مصروف، وقراءة وضعه الضريبي دون خبرة محاسبية مسبقة.'],
            ['Is my data secure?', 'هل بياناتي آمنة؟', "Every company's data is isolated from every other company at the database level, passwords are hashed, and all traffic should run over HTTPS in production.", 'بيانات كل منشأة معزولة تمامًا عن بيانات المنشآت الأخرى على مستوى قاعدة البيانات، وكلمات المرور مشفّرة، وينبغي أن تمر جميع البيانات عبر HTTPS في بيئة الإنتاج.'],
        ];
        foreach ($qa as $i => [$qEn, $qAr, $aEn, $aAr]) {
            $this->item($faq, $i + 1, ['title_en' => $qEn, 'title_ar' => $qAr, 'body_en' => $aEn, 'body_ar' => $aAr]);
        }

        $this->section('compliance', 'cta', 4, [
            'title_en' => 'Ready to simplify your VAT invoicing?',
            'title_ar' => 'هل أنت مستعد لتبسيط فوترتك الضريبية؟',
            'subtitle_en' => 'Join Saudi businesses managing their invoicing and VAT with Daftari.',
            'subtitle_ar' => 'انضم إلى الشركات السعودية التي تدير فواتيرها وضريبتها عبر دفتري.',
            'link_text_en' => 'Start your free trial',
            'link_text_ar' => 'ابدأ تجربتك المجانية',
        ]);
    }

    /**
     * Fully idempotent per-section — see the doc comment on seedAbout().
     * An earlier data migration already creates the contact page's faq
     * section (with its first 3 questions) before this seeder ever runs
     * on a fresh install, so the hero/contact_info/social_links sections
     * below must be created independently of that, and the faq section
     * itself must be reused rather than duplicated.
     */
    private function seedContact(): void
    {
        $hero = CmsSection::query()->where('page', 'contact')->where('type', 'hero')->first()
            ?? $this->section('contact', 'hero', 1, [
                'title_en' => 'Contact us',
                'title_ar' => 'تواصل معنا',
                'subtitle_en' => 'Our team is ready to help. Choose what works best for you.',
                'subtitle_ar' => 'فريقنا جاهز للمساعدة. اختر الطريقة الأنسب لك.',
            ]);

        $methods = CmsSection::query()->where('page', 'contact')->where('type', 'contact_info')->first()
            ?? $this->section('contact', 'contact_info', 2);
        $methodRows = [
            ['✉️', 'Email', 'البريد الإلكتروني', 'For support and general inquiries', 'للدعم والاستفسارات العامة', 'support@daftari.app', 'support@daftari.app', 'mailto:support@daftari.app'],
            ['📞', 'Phone', 'الجوال', 'Call us during business hours', 'اتصل بنا خلال ساعات العمل', '+966 11 000 0000', '+966 11 000 0000', 'tel:+966110000000'],
            ['💬', 'WhatsApp', 'واتساب', 'Message us directly on WhatsApp', 'راسلنا مباشرة عبر واتساب', '+966 50 000 0000', '+966 50 000 0000', '#'],
            ['🤝', 'Sales', 'المبيعات', 'Questions before you buy, or need a plan recommendation', 'أسئلة قبل الشراء أو تحتاج توصية بالباقة المناسبة', 'sales@daftari.app', 'sales@daftari.app', 'mailto:sales@daftari.app'],
        ];
        foreach ($methodRows as $i => [$icon, $tEn, $tAr, $sEn, $sAr, $bEn, $bAr, $url]) {
            if ($methods->items()->where('title_en', $tEn)->exists()) {
                continue;
            }
            $this->item($methods, $i + 1, ['icon' => $icon, 'title_en' => $tEn, 'title_ar' => $tAr, 'subtitle_en' => $sEn, 'subtitle_ar' => $sAr, 'body_en' => $bEn, 'body_ar' => $bAr, 'meta' => ['url' => $url]]);
        }

        // Hidden by default — no real profile URLs to publish yet. An admin
        // turns this on from Website CMS once they've added real links.
        $social = CmsSection::query()->where('page', 'contact')->where('type', 'social_links')->first()
            ?? $this->section('contact', 'social_links', 3, [
                'title_en' => 'Follow us',
                'title_ar' => 'تابعنا',
                'is_active' => false,
            ]);

        $faq = CmsSection::query()->where('page', 'contact')->where('type', 'faq')->first()
            ?? $this->section('contact', 'faq', 4, [
                'title_en' => 'Before you reach out',
                'title_ar' => 'قبل أن تتواصل معنا',
            ]);
        $contactQa = [
            ['How quickly do you respond?', 'ما مدى سرعة الرد؟', 'We aim to respond to support and sales messages within one business day.', 'نسعى للرد على رسائل الدعم والمبيعات خلال يوم عمل واحد.'],
            ['I have a question about my subscription or an invoice.', 'لدي سؤال حول اشتراكي أو إحدى الفواتير.', 'Email support with your company name and, if relevant, the invoice or payment reference — that lets us look into it right away.', 'راسلنا عبر البريد الإلكتروني مع اسم منشأتك، وإن كان الأمر متعلقًا بفاتورة أو دفعة، أرفق المرجع الخاص بها — ليتسنى لنا معالجة الأمر فورًا.'],
            ['Can I get help setting up ZATCA Phase 2?', 'هل يمكنني الحصول على مساعدة لإعداد المرحلة الثانية من هيئة الزكاة؟', 'Yes — message us and we\'ll walk you through connecting your ZATCA credentials from the ZATCA section of your dashboard.', 'نعم — راسلنا وسنرشدك خطوة بخطوة لربط بيانات اعتماد هيئة الزكاة من قسم ZATCA في لوحة التحكم.'],
            ['Does Daftari include payroll and a point of sale?', 'هل يشمل دفتري الرواتب ونقطة البيع؟', 'Yes — full WPS-compliant payroll with GOSI and end-of-service calculations, and a retail point of sale with split payments and ZATCA receipts, are both built into the platform on qualifying plans.', 'نعم — الرواتب الكاملة المتوافقة مع نظام حماية الأجور مع حسابات التأمينات ومكافأة نهاية الخدمة، ونقطة بيع للتجزئة مع دفع مقسّم وإيصالات متوافقة مع هيئة الزكاة، كلاهما مدمج في المنصة ضمن الباقات المؤهلة.'],
            ['Can I try Daftari with my team before deciding?', 'هل يمكنني تجربة دفتري مع فريقي قبل اتخاذ القرار؟', 'Yes — every plan starts with a free trial, no credit card required, so you and your team can try real invoicing, payroll, and POS workflows before you commit.', 'نعم — تبدأ كل باقة بتجربة مجانية دون الحاجة لبطاقة ائتمان، حتى تتمكن أنت وفريقك من تجربة سير عمل حقيقي للفوترة والرواتب ونقطة البيع قبل الالتزام.'],
        ];
        foreach ($contactQa as $i => [$qEn, $qAr, $aEn, $aAr]) {
            if ($faq->items()->where('title_en', $qEn)->exists()) {
                continue;
            }
            $this->item($faq, $i + 1, ['title_en' => $qEn, 'title_ar' => $qAr, 'body_en' => $aEn, 'body_ar' => $aAr]);
        }

        // See the matching comment at the end of seedAbout() — pin the
        // final display order explicitly since some of these sections
        // can be found rather than freshly created.
        foreach ([$hero, $methods, $social, $faq] as $order => $section) {
            $section->update(['sort_order' => $order + 1]);
        }
    }

    private function seedGlobal(): void
    {
        $this->section('global', 'site_header', 1, ['is_active' => false]);

        $this->section('global', 'site_footer', 2, [
            'subtitle_en' => 'Subscription accounting & VAT e-invoicing for Saudi businesses.',
            'subtitle_ar' => 'محاسبة بالاشتراك وفوترة إلكترونية لضريبة القيمة المضافة للشركات السعودية.',
        ]);

        $this->seedMainNav();
        $this->seedFooterLinks();
    }

    /**
     * Mirrors the add_header_nav_and_footer_links_cms_blocks data migration,
     * which carries this same content to an existing install — keep both in
     * sync.
     */
    private function seedMainNav(): void
    {
        $nav = $this->section('global', 'main_nav', 3);

        $links = [
            ['Features', 'المزايا', 'features'],
            ['Pricing', 'الأسعار', 'pricing'],
            ['Compliance', 'الامتثال', 'compliance'],
            ['About', 'من نحن', 'about'],
            ['Contact', 'تواصل معنا', 'contact'],
        ];

        foreach ($links as $i => [$tEn, $tAr, $routeName]) {
            $this->item($nav, $i + 1, [
                'title_en' => $tEn,
                'title_ar' => $tAr,
                'meta' => ['url' => route($routeName, [], false)],
            ]);
        }
    }

    private function seedFooterLinks(): void
    {
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

        $order = 3;
        foreach ($columns as $titleEn => $column) {
            $order++;

            $section = $this->section('global', 'footer_links', $order, [
                'title_en' => $titleEn,
                'title_ar' => $column['ar'],
            ]);

            foreach ($column['links'] as $i => [$tEn, $tAr, $route]) {
                $url = is_array($route) ? route($route[0], $route[1], false) : route($route, [], false);

                $this->item($section, $i + 1, [
                    'title_en' => $tEn,
                    'title_ar' => $tAr,
                    'meta' => ['url' => $url],
                ]);
            }
        }
    }

    private function section(string $page, string $type, int $order, array $attrs = []): CmsSection
    {
        return CmsSection::create(array_merge([
            'page' => $page,
            'type' => $type,
            'sort_order' => $order,
            'is_active' => true,
        ], $attrs));
    }

    private function item(CmsSection $section, int $order, array $attrs): void
    {
        $section->items()->create(array_merge(['sort_order' => $order, 'is_active' => true], $attrs));
    }
}
