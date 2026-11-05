<?php

namespace Database\Seeders;

use App\Models\CmsPage;
use App\Models\CmsSection;
use Illuminate\Database\Seeder;

/**
 * Seeds one marketing landing page per industry (item 2 of the Sales,
 * Compliance & Business Growth request: "industry landing pages"), built
 * entirely from the existing custom-page CMS (CmsPage + CmsSection —
 * see CmsPageController/admin CmsController) rather than a parallel
 * system: each page is a normal admin-editable page an operator can
 * reword, re-order, or delete like any other. Its call-to-action links to
 * /get-started with the industry pre-tagged, so every signup started from
 * one of these pages lands in the CRM already labeled with where it came
 * from. Generic product-capability copy only — no fabricated customer
 * testimonials or quotes, consistent with the rest of this codebase's
 * "never claim what isn't true" approach to marketing content.
 *
 * Idempotent per page slug, so re-running never duplicates content an
 * admin has since edited or removed.
 */
class IndustryLandingPageSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->pages() as $slug => $page) {
            if (CmsPage::query()->where('slug', $slug)->exists()) {
                continue;
            }

            $cmsPage = CmsPage::create([
                'slug' => $slug,
                'name_en' => $page['name_en'],
                'name_ar' => $page['name_ar'],
                'is_system' => false,
                'is_active' => true,
                'show_in_footer' => true,
                'show_in_menu' => false,
                'sort_order' => $page['sort_order'],
            ]);

            $this->section($slug, 'text', 1, [
                'title_en' => $page['intro_title_en'],
                'title_ar' => $page['intro_title_ar'],
                'body_en' => $page['intro_body_en'],
                'body_ar' => $page['intro_body_ar'],
            ]);

            $features = $this->section($slug, 'feature_grid', 2, [
                'title_en' => "Built for how {$page['name_en']} works",
                'title_ar' => "مصمم لطريقة عمل {$page['name_ar']}",
            ]);
            foreach ($page['features'] as $i => [$icon, $titleEn, $titleAr, $bodyEn, $bodyAr]) {
                $features->items()->create([
                    'sort_order' => $i + 1,
                    'is_active' => true,
                    'icon' => $icon,
                    'title_en' => $titleEn,
                    'title_ar' => $titleAr,
                    'body_en' => $bodyEn,
                    'body_ar' => $bodyAr,
                ]);
            }

            $this->section($slug, 'cta', 3, [
                'title_en' => "See {$page['name_en']} accounting in action",
                'title_ar' => "شاهد محاسبة {$page['name_ar']} في العمل",
                'subtitle_en' => 'Request a free demo tailored to your business — no credit card required.',
                'subtitle_ar' => 'اطلب عرضًا توضيحيًا مجانيًا مخصصًا لأعمالك — دون الحاجة لبطاقة ائتمان.',
                'link_url' => route('get-started', ['industry' => $page['industry'], 'source' => 'get_started']),
                'link_text_en' => 'Request a demo',
                'link_text_ar' => 'اطلب عرضًا توضيحيًا',
            ]);

            $cmsPage->touch();
        }
    }

    private function section(string $page, string $type, int $order, array $attrs): CmsSection
    {
        return CmsSection::create(array_merge([
            'page' => $page,
            'type' => $type,
            'sort_order' => $order,
            'is_active' => true,
        ], $attrs));
    }

    /**
     * @return array<string, array{industry: string, sort_order: int, name_en: string, name_ar: string, intro_title_en: string, intro_title_ar: string, intro_body_en: string, intro_body_ar: string, features: array<int, array{0: string, 1: string, 2: string, 3: string, 4: string}>}>
     */
    private function pages(): array
    {
        return [
            'solutions-contracting' => [
                'industry' => 'contracting',
                'sort_order' => 50,
                'name_en' => 'Contracting & Construction',
                'name_ar' => 'المقاولات والإنشاءات',
                'intro_title_en' => 'Accounting and VAT invoicing for Saudi contractors',
                'intro_title_ar' => 'محاسبة وفوترة ضريبية لمقاولي البناء في السعودية',
                'intro_body_en' => 'Track project costs and margins, issue ZATCA-compliant invoices with retention, and manage subcontractor bills and purchase orders — all in one platform built for construction and contracting businesses.',
                'intro_body_ar' => 'تتبّع تكاليف المشاريع وهوامش الربح، وأصدر فواتير متوافقة مع هيئة الزكاة والضريبة والجمارك مع دعم الاحتجاز، وأدر فواتير المقاولين من الباطن وأوامر الشراء — كل ذلك في منصة واحدة مصممة لشركات البناء والمقاولات.',
                'features' => [
                    ['projects', 'Project cost & margin tracking', 'تتبع تكاليف المشاريع وهوامش الربح', 'See revenue, cost, and margin for every project as it progresses, not just at close-out.', 'اطّلع على الإيرادات والتكاليف والهامش لكل مشروع أثناء تقدمه، وليس فقط عند إغلاقه.'],
                    ['zatca', 'Retention-aware invoicing', 'فوترة تدعم الاحتجاز', 'Issue tax invoices with retention rate and amount handled correctly, posted to the right accounts automatically.', 'أصدر فواتير ضريبية مع نسبة ومبلغ احتجاز صحيحين، تُرحّل تلقائيًا إلى الحسابات الصحيحة.'],
                    ['purchases', 'Purchase orders & subcontractor bills', 'أوامر الشراء وفواتير مقاولي الباطن', 'Document purchase requests and organize subcontractor and supplier costs for accurate project reporting.', 'وثّق طلبات الشراء ونظّم تكاليف مقاولي الباطن والموردين لتقارير مشاريع دقيقة.'],
                ],
            ],
            'solutions-trading' => [
                'industry' => 'trading',
                'sort_order' => 51,
                'name_en' => 'Trading',
                'name_ar' => 'التجارة',
                'intro_title_en' => 'Accounting built for import, export & wholesale trading',
                'intro_title_ar' => 'محاسبة مصممة لتجارة الاستيراد والتصدير والجملة',
                'intro_body_en' => 'Manage inventory across warehouses, track customs declarations on your purchases, and keep your VAT position clear across every sale and shipment.',
                'intro_body_ar' => 'أدر المخزون عبر المستودعات، وتتبّع الإقرارات الجمركية على مشترياتك، وحافظ على وضوح موقفك الضريبي عبر كل عملية بيع وشحنة.',
                'features' => [
                    ['items', 'Multi-warehouse inventory', 'مخزون متعدد المستودعات', 'Track stock across warehouses and branches, with automatic deduction on every sale.', 'تتبّع المخزون عبر المستودعات والفروع، مع خصم تلقائي عند كل عملية بيع.'],
                    ['purchases', 'Customs declarations', 'الإقرارات الجمركية', 'Record customs declarations on your import purchases for accurate landed-cost and VAT reporting.', 'سجّل الإقرارات الجمركية على مشترياتك المستوردة لتقارير دقيقة للتكلفة النهائية وضريبة القيمة المضافة.'],
                    ['reports', 'Sales & VAT reporting', 'تقارير المبيعات وضريبة القيمة المضافة', 'A clear VAT return summary and sales reports covering every invoice, branch, and period.', 'ملخص واضح لإقرار ضريبة القيمة المضافة وتقارير مبيعات تغطي كل فاتورة وفرع وفترة.'],
                ],
            ],
            'solutions-retail' => [
                'industry' => 'retail',
                'sort_order' => 52,
                'name_en' => 'Retail',
                'name_ar' => 'التجزئة',
                'intro_title_en' => 'A fast checkout counter with accounting built in',
                'intro_title_ar' => 'شاشة دفع سريعة مع محاسبة مدمجة',
                'intro_body_en' => 'Ring up sales at the counter with a touch-friendly point of sale, reconcile every shift, and see stock and VAT update automatically behind the scenes.',
                'intro_body_ar' => 'سجّل المبيعات عند المنضدة عبر شاشة دفع سهلة اللمس، وسوِّ كل وردية، وشاهد المخزون وضريبة القيمة المضافة يتحدثان تلقائيًا في الخلفية.',
                'features' => [
                    ['pos', 'Touch-friendly point of sale', 'نقطة بيع سهلة اللمس', 'Barcode scanning, split cash/card payments, and a ZATCA-compliant QR receipt on every sale.', 'مسح الباركود، ودفع مقسّم نقدًا وبالبطاقة، وإيصال بيع يتضمن رمز QR متوافقًا مع هيئة الزكاة والضريبة والجمارك.'],
                    ['clock', 'Register shifts & Z-report', 'ورديات الصندوق وتقرير Z', 'Open and close cash register shifts with an automatic reconciliation report.', 'افتح وأغلق ورديات صندوق النقد مع تقرير تسوية تلقائي.'],
                    ['items', 'Live inventory', 'مخزون لحظي', 'Stock is deducted automatically on every sale, so what you see on hand is always current.', 'يُخصم المخزون تلقائيًا عند كل عملية بيع، فما تراه متوفرًا هو دائمًا محدث.'],
                ],
            ],
            'solutions-restaurants' => [
                'industry' => 'restaurants',
                'sort_order' => 53,
                'name_en' => 'Restaurants',
                'name_ar' => 'المطاعم',
                'intro_title_en' => 'Point of sale and accounting for restaurants & cafés',
                'intro_title_ar' => 'نقطة بيع ومحاسبة للمطاعم والمقاهي',
                'intro_body_en' => 'A fast counter checkout for busy service, with every sale posted to your books and a compliant QR receipt for every customer.',
                'intro_body_ar' => 'شاشة دفع سريعة لأوقات الذروة، مع ترحيل كل عملية بيع إلى سجلاتك المحاسبية وإيصال متوافق برمز QR لكل عميل.',
                'features' => [
                    ['pos', 'Fast counter checkout', 'دفع سريع عند المنضدة', 'A touch-friendly point of sale built for high-volume, fast-paced service.', 'شاشة دفع سهلة اللمس مصممة لخدمة سريعة وحجم مبيعات مرتفع.'],
                    ['branches', 'Multi-branch tracking', 'تتبع متعدد الفروع', 'Track sales, expenses, and performance separately for each branch or location.', 'تتبّع المبيعات والمصروفات والأداء بشكل منفصل لكل فرع أو موقع.'],
                    ['accounting', 'Automatic GL posting', 'ترحيل تلقائي إلى دفتر الأستاذ', 'Every sale, refund, and shift close posts to the correct accounts automatically.', 'يُرحّل كل بيع واسترداد وإغلاق وردية تلقائيًا إلى الحسابات الصحيحة.'],
                ],
            ],
            'solutions-auto-workshops' => [
                'industry' => 'auto_workshops',
                'sort_order' => 54,
                'name_en' => 'Auto Workshops',
                'name_ar' => 'ورش السيارات',
                'intro_title_en' => 'Invoicing and parts tracking for auto workshops',
                'intro_title_ar' => 'فوترة وتتبع قطع غيار لورش السيارات',
                'intro_body_en' => 'Issue compliant invoices for labor and parts, track spare-parts stock, and keep a clear record of every customer and job.',
                'intro_body_ar' => 'أصدر فواتير متوافقة للعمالة وقطع الغيار، وتتبّع مخزون قطع الغيار، واحتفظ بسجل واضح لكل عميل ومهمة.',
                'features' => [
                    ['sales', 'Service invoices', 'فواتير الخدمة', 'Bill for labor and parts on one compliant VAT invoice, sent straight to the customer.', 'فوتر العمالة وقطع الغيار في فاتورة ضريبية واحدة متوافقة، تُرسل مباشرة للعميل.'],
                    ['items', 'Spare-parts inventory', 'مخزون قطع الغيار', 'Track parts stock so you know what is on hand before promising a repair date.', 'تتبّع مخزون القطع لتعرف المتوفر قبل تحديد موعد الإصلاح.'],
                    ['team', 'Customer & vehicle history', 'سجل العملاء والمركبات', 'Keep a running record of every customer, job, and invoice for faster repeat service.', 'احتفظ بسجل مستمر لكل عميل ومهمة وفاتورة لخدمة أسرع عند التكرار.'],
                ],
            ],
            'solutions-services' => [
                'industry' => 'services',
                'sort_order' => 55,
                'name_en' => 'Services',
                'name_ar' => 'الخدمات',
                'intro_title_en' => 'Invoicing and client management for service businesses',
                'intro_title_ar' => 'فوترة وإدارة عملاء لشركات الخدمات',
                'intro_body_en' => 'Send quotations, convert them to compliant invoices once approved, and track recurring billing for retainer clients automatically.',
                'intro_body_ar' => 'أرسل عروض الأسعار، وحوّلها إلى فواتير متوافقة بعد الموافقة، وتتبّع الفوترة المتكررة لعملاء العقود الدورية تلقائيًا.',
                'features' => [
                    ['templates', 'Quotations & proforma invoices', 'عروض الأسعار والفواتير الأولية', 'Send a professional quote and convert it straight into an invoice once a client approves.', 'أرسل عرض سعر احترافيًا وحوّله مباشرة إلى فاتورة بعد موافقة العميل.'],
                    ['clock', 'Recurring invoices', 'الفواتير المتكررة', 'Set up a billing schedule once for retainer or subscription clients and let it run automatically.', 'أعدّ جدول فوترة مرة واحدة لعملاء العقود أو الاشتراكات ودعه يعمل تلقائيًا.'],
                    ['team', 'Client management', 'إدارة العملاء', 'Keep full contact, VAT, and billing details for every client in one place.', 'احتفظ بكامل تفاصيل التواصل والضريبة والفوترة لكل عميل في مكان واحد.'],
                ],
            ],
        ];
    }
}
