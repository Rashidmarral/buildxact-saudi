<?php

use App\Models\CmsSection;
use Illuminate\Database\Migrations\Migration;

/**
 * A data migration (not schema) so this content update reaches every
 * existing install — including a buyer's own local database — with a
 * plain `php artisan migrate`, the same way CmsContentSeeder seeded the
 * original content. Every step below is guarded by an existence check, so
 * running this on a database that already has the content (or where an
 * admin has since customized it) is a safe no-op rather than a duplicate
 * insert or a silent overwrite of their edits.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->addGlobalHeaderAndFooter();
        $this->expandHomeFeatureGrid();
        $this->enrichAboutPage();
        $this->addContactFaq();
    }

    public function down(): void
    {
        // Deliberately no-op: this only ever adds rows guarded by
        // existence checks, and an admin may have edited them since —
        // reversing could delete real customizations.
    }

    private function addGlobalHeaderAndFooter(): void
    {
        if (! CmsSection::query()->where('page', 'global')->where('type', 'site_header')->exists()) {
            CmsSection::create([
                'page' => 'global',
                'type' => 'site_header',
                'sort_order' => 1,
                'is_active' => false,
            ]);
        }

        if (! CmsSection::query()->where('page', 'global')->where('type', 'site_footer')->exists()) {
            CmsSection::create([
                'page' => 'global',
                'type' => 'site_footer',
                'sort_order' => 2,
                'is_active' => true,
                'subtitle_en' => 'Subscription accounting & VAT e-invoicing for Saudi businesses.',
                'subtitle_ar' => 'محاسبة بالاشتراك وفوترة إلكترونية لضريبة القيمة المضافة للشركات السعودية.',
            ]);
        }
    }

    /**
     * Home originally launched with 6 hand-picked highlight cards; this
     * grows it to the full list of implemented modules (matching the
     * Features page) so the homepage doesn't undersell everything that's
     * shipped since. Guarded by item count rather than exact content, so
     * it still applies once even if an admin already tweaked a couple of
     * the original 6 cards' wording.
     */
    private function expandHomeFeatureGrid(): void
    {
        $section = CmsSection::query()->where('page', 'home')->where('type', 'feature_grid')->first();

        if (! $section || $section->items()->count() >= 15) {
            return;
        }

        $section->items()->delete();

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
            ['🌍', 'Bilingual, RTL-ready', 'ثنائي اللغة، وجاهز للعرض من اليمين لليسار', 'Full Arabic and English interface with proper right-to-left layout.', 'واجهة كاملة بالعربية والإنجليزية مع تخطيط صحيح من اليمين إلى اليسار.'],
            ['🔔', 'In-app notifications & 2FA', 'الإشعارات داخل التطبيق والتحقق بخطوتين', 'Stay on top of what needs attention, and secure your account with two-factor authentication.', 'تابع كل ما يحتاج انتباهك، وأمّن حسابك بالتحقق بخطوتين.'],
        ];

        foreach ($rows as $i => [$icon, $tEn, $tAr, $bEn, $bAr]) {
            $section->items()->create([
                'sort_order' => $i + 1,
                'is_active' => true,
                'icon' => $icon,
                'title_en' => $tEn,
                'title_ar' => $tAr,
                'body_en' => $bEn,
                'body_ar' => $bAr,
            ]);
        }
    }

    private function enrichAboutPage(): void
    {
        if (CmsSection::query()->where('page', 'about')->where('title_en', 'How we build Daftari')->exists()) {
            return;
        }

        $maxOrder = (int) CmsSection::query()->where('page', 'about')->max('sort_order');

        CmsSection::create([
            'page' => 'about',
            'type' => 'text',
            'sort_order' => $maxOrder + 1,
            'is_active' => true,
            'title_en' => 'How we build Daftari',
            'title_ar' => 'كيف نبني دفتري',
            'body_en' => "We ship the modules a growing Saudi business actually needs — invoicing, purchasing, inventory, accounting, and ZATCA compliance — as one connected product instead of bolted-together add-ons.\n\nSupport comes from people who understand Saudi VAT and e-invoicing, not a generic help desk, so when a question is specific to your business, you get a specific answer.",
            'body_ar' => "نبني الوحدات التي تحتاجها فعليًا منشأة سعودية نامية — الفوترة والمشتريات والمخزون والمحاسبة والامتثال لهيئة الزكاة والضريبة والجمارك — كمنتج واحد متكامل بدلًا من إضافات منفصلة.\n\nيأتي الدعم من أشخاص يفهمون ضريبة القيمة المضافة والفوترة الإلكترونية في السعودية، لا مركز دعم عام، فحين يكون سؤالك خاصًا بمنشأتك، تحصل على إجابة محددة.",
            'image_position' => 'right',
        ]);
    }

    private function addContactFaq(): void
    {
        if (CmsSection::query()->where('page', 'contact')->where('type', 'faq')->exists()) {
            return;
        }

        $maxOrder = (int) CmsSection::query()->where('page', 'contact')->max('sort_order');

        $section = CmsSection::create([
            'page' => 'contact',
            'type' => 'faq',
            'sort_order' => $maxOrder + 1,
            'is_active' => true,
            'title_en' => 'Before you reach out',
            'title_ar' => 'قبل أن تتواصل معنا',
        ]);

        $qa = [
            ['How quickly do you respond?', 'ما مدى سرعة الرد؟', 'We aim to respond to support and sales messages within one business day.', 'نسعى للرد على رسائل الدعم والمبيعات خلال يوم عمل واحد.'],
            ['I have a question about my subscription or an invoice.', 'لدي سؤال حول اشتراكي أو إحدى الفواتير.', 'Email support with your company name and, if relevant, the invoice or payment reference — that lets us look into it right away.', 'راسلنا عبر البريد الإلكتروني مع اسم منشأتك، وإن كان الأمر متعلقًا بفاتورة أو دفعة، أرفق المرجع الخاص بها — ليتسنى لنا معالجة الأمر فورًا.'],
            ['Can I get help setting up ZATCA Phase 2?', 'هل يمكنني الحصول على مساعدة لإعداد المرحلة الثانية من هيئة الزكاة؟', 'Yes — message us and we\'ll walk you through connecting your ZATCA credentials from the ZATCA section of your dashboard.', 'نعم — راسلنا وسنرشدك خطوة بخطوة لربط بيانات اعتماد هيئة الزكاة من قسم ZATCA في لوحة التحكم.'],
        ];

        foreach ($qa as $i => [$qEn, $qAr, $aEn, $aAr]) {
            $section->items()->create([
                'sort_order' => $i + 1,
                'is_active' => true,
                'title_en' => $qEn,
                'title_ar' => $qAr,
                'body_en' => $aEn,
                'body_ar' => $aAr,
            ]);
        }
    }
};
