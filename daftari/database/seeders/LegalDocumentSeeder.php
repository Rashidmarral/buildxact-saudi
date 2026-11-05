<?php

namespace Database\Seeders;

use App\Models\LegalDocument;
use Illuminate\Database\Seeder;

/**
 * Drafted starting points for the platform's legal documents — every one
 * of these is marked requires_legal_review = true and must be checked by
 * a licensed Saudi legal advisor before being relied on as enforceable.
 * Terms/Privacy carry the same content that used to be hardcoded in
 * site/legal.blade.php (now rendered from here instead); the other six
 * are new. Idempotent per-slug: never overwrites a document an admin has
 * since edited.
 */
class LegalDocumentSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->documents() as $i => $doc) {
            if (LegalDocument::where('slug', $doc['slug'])->exists()) {
                continue;
            }

            LegalDocument::create([
                'slug' => $doc['slug'],
                'title_en' => $doc['title_en'],
                'title_ar' => $doc['title_ar'],
                'body_en' => $doc['body_en'],
                'body_ar' => $doc['body_ar'],
                'status' => $doc['status'],
                'requires_legal_review' => true,
                'sort_order' => $i + 1,
            ]);
        }
    }

    private function documents(): array
    {
        return [
            $this->terms(),
            $this->privacy(),
            $this->cookiePolicy(),
            $this->subscriptionAgreement(),
            $this->refundPolicy(),
            $this->dataProcessingAgreement(),
            $this->customerResponsibilities(),
            $this->zatcaDisclaimer(),
        ];
    }

    private function terms(): array
    {
        return [
            'slug' => 'terms', 'status' => 'published',
            'title_en' => 'Terms of Service', 'title_ar' => 'شروط الخدمة',
            'body_en' => '<p>Welcome to Daftari. These Terms of Service ("Terms") are a legal agreement between you (or the business you represent) and Daftari governing your access to and use of the Daftari accounting and e-invoicing platform (the "Service"). By creating an account or otherwise using the Service, you agree to be bound by these Terms. If you are agreeing on behalf of a company, you confirm that you are authorized to bind that company.</p>'
                .'<h2>1. The Service</h2><p>Daftari provides cloud-based accounting, invoicing, expense, inventory, payroll, point-of-sale, and e-invoicing tools for businesses operating in Saudi Arabia, designed to support applicable ZATCA e-invoicing requirements. The Service is provided on a subscription basis, and features available to you depend on your selected plan.</p>'
                .'<h2>2. Your account</h2><p>You are responsible for maintaining the confidentiality of your account credentials and for all activity that occurs under your account. Notify us immediately if you suspect unauthorized access. You must provide accurate registration information and keep it up to date.</p>'
                .'<h2>3. Subscriptions and billing</h2><p>Plans, pricing, and billing cycles are described on our Pricing page and in the Subscription Agreement. Subscriptions renew automatically for successive billing periods unless cancelled before the renewal date. Fees are charged in advance and, except where required by law or expressly stated otherwise, are non-refundable — see the Refund &amp; Cancellation Policy. We may change our pricing or plans; we will give you reasonable advance notice before any change takes effect on your account.</p>'
                .'<h2>4. Your data</h2><p>You retain all ownership rights to the invoices, clients, financial records, and other business data you enter into or generate through Daftari ("Your Data"). You grant us a limited license to host, process, and transmit Your Data solely to provide and support the Service. We do not sell Your Data to third parties. On termination of your account, you may export Your Data, and we will retain a copy for a reasonable period thereafter as described in our Privacy Policy before permanent deletion.</p>'
                .'<h2>5. ZATCA e-invoicing and regulatory compliance</h2><p>Daftari helps you generate, format, and transmit e-invoices designed to support the Zakat, Tax and Customs Authority\'s (ZATCA) e-invoicing regulations. You remain solely responsible for the accuracy of the tax, VAT, and business information you enter, for your own compliance with Saudi tax law and ZATCA requirements, and for any filings made using data from the Service. Daftari is a software tool, not a substitute for qualified tax, legal, or accounting advice — see also the ZATCA &amp; Customer Responsibility Disclaimer.</p>'
                .'<h2>6. Acceptable use</h2><p>You agree not to: use the Service for any unlawful purpose or to submit fraudulent tax documents; attempt to disrupt, reverse-engineer, or gain unauthorized access to the Service or other customers\' data; upload malicious code; or use the Service in a way that violates the rights of any third party.</p>'
                .'<h2>7. Third-party services</h2><p>The Service may integrate with third-party providers you choose to configure, such as payment gateways, SMS/WhatsApp messaging providers, and email delivery services. Your use of those integrations is also subject to that third party\'s own terms, and Daftari is not responsible for their acts or omissions.</p>'
                .'<h2>8. Intellectual property</h2><p>Daftari and its licensors retain all rights, title, and interest in the Service itself, including its software, design, and branding. Nothing in these Terms transfers any of that intellectual property to you, other than the limited right to use the Service as intended.</p>'
                .'<h2>9. Termination</h2><p>You may cancel your subscription at any time from your account settings. We may suspend or terminate your access if you materially breach these Terms, fail to pay applicable fees, or if required to do so by law. On termination, your right to use the Service ends, though certain provisions of these Terms (such as data handling, liability, and governing law) survive.</p>'
                .'<h2>10. Disclaimer and limitation of liability</h2><p>The Service is provided "as is" and "as available." To the fullest extent permitted by law, Daftari disclaims all warranties, express or implied, and shall not be liable for indirect, incidental, or consequential damages arising from your use of the Service. Our aggregate liability for any claim relating to the Service is limited to the fees you paid us in the twelve months preceding the claim.</p>'
                .'<h2>11. Governing law</h2><p>These Terms are governed by the laws of the Kingdom of Saudi Arabia. Any dispute arising from these Terms or your use of the Service shall be subject to the exclusive jurisdiction of the competent courts of Saudi Arabia.</p>'
                .'<h2>12. Changes to these Terms</h2><p>We may update these Terms from time to time. We will post the updated Terms on this page with a revised "Last updated" date, and for material changes we will provide additional notice (such as email or an in-app notice). Continued use of the Service after a change takes effect constitutes acceptance of the revised Terms.</p>'
                .'<h2>13. Contact us</h2><p>Questions about these Terms can be sent through our Contact page.</p>',
            'body_ar' => '<p>مرحبًا بك في دفتري. تُعد شروط الخدمة هذه ("الشروط") اتفاقية قانونية بينك (أو المنشأة التي تمثلها) وبين دفتري، تحكم وصولك إلى منصة دفتري للمحاسبة والفوترة الإلكترونية ("الخدمة") واستخدامك لها. بإنشائك حسابًا أو استخدامك للخدمة بأي شكل آخر، فإنك توافق على الالتزام بهذه الشروط. إذا كنت توافق نيابة عن شركة، فإنك تقر بأنك مخوَّل بإلزامها.</p>'
                .'<h2>1. الخدمة</h2><p>يوفر دفتري أدوات سحابية للمحاسبة والفوترة والمصروفات والمخزون والرواتب ونقطة البيع والفوترة الإلكترونية للأعمال العاملة في المملكة العربية السعودية، مصممة لدعم متطلبات الفوترة الإلكترونية المعمول بها لدى هيئة الزكاة والضريبة والجمارك. تُقدَّم الخدمة على أساس الاشتراك، وتعتمد الميزات المتاحة لك على الباقة التي تختارها.</p>'
                .'<h2>2. حسابك</h2><p>أنت مسؤول عن الحفاظ على سرية بيانات اعتماد حسابك وعن كل نشاط يتم تحت حسابك. أبلغنا فورًا إذا اشتبهت بوصول غير مصرح به. يجب عليك تقديم معلومات تسجيل دقيقة وتحديثها باستمرار.</p>'
                .'<h2>3. الاشتراكات والفوترة</h2><p>الباقات والأسعار ودورات الفوترة موضحة في صفحة الأسعار وفي اتفاقية الاشتراك. تُجدَّد الاشتراكات تلقائيًا لفترات فوترة متتالية ما لم يتم إلغاؤها قبل تاريخ التجديد. تُحصَّل الرسوم مقدمًا، وباستثناء ما يقتضيه القانون أو ما يُنص عليه صراحةً خلاف ذلك، فهي غير قابلة للاسترداد — راجع سياسة الاسترداد والإلغاء. قد نغيّر أسعارنا أو باقاتنا؛ وسنقدم إشعارًا مسبقًا معقولًا قبل سريان أي تغيير على حسابك.</p>'
                .'<h2>4. بياناتك</h2><p>تحتفظ بجميع حقوق ملكية الفواتير والعملاء والسجلات المالية وغيرها من بيانات الأعمال التي تُدخلها أو تنشئها عبر دفتري ("بياناتك"). تمنحنا ترخيصًا محدودًا لاستضافة بياناتك ومعالجتها ونقلها فقط لتقديم الخدمة ودعمها. لا نبيع بياناتك لأطراف ثالثة. عند إنهاء حسابك، يمكنك تصدير بياناتك، وسنحتفظ بنسخة منها لفترة معقولة بعد ذلك كما هو موضح في سياسة الخصوصية قبل حذفها نهائيًا.</p>'
                .'<h2>5. الفوترة الإلكترونية والامتثال التنظيمي</h2><p>يساعدك دفتري في إنشاء وتنسيق ونقل فواتير إلكترونية مصممة لدعم لوائح الفوترة الإلكترونية لهيئة الزكاة والضريبة والجمارك. تظل مسؤولاً وحدك عن دقة المعلومات الضريبية ومعلومات ضريبة القيمة المضافة ومعلومات منشأتك التي تُدخلها، وعن امتثالك الخاص لنظام الضريبة السعودي ومتطلبات الهيئة، وعن أي إقرارات تُقدَّم باستخدام بيانات من الخدمة. دفتري أداة برمجية، وليس بديلاً عن استشارة ضريبية أو قانونية أو محاسبية مؤهلة — راجع أيضًا إخلاء مسؤولية الهيئة ومسؤولية العميل.</p>'
                .'<h2>6. الاستخدام المقبول</h2><p>توافق على عدم: استخدام الخدمة لأي غرض غير قانوني أو لتقديم مستندات ضريبية احتيالية؛ محاولة تعطيل الخدمة أو الهندسة العكسية لها أو الوصول غير المصرح به إليها أو إلى بيانات عملاء آخرين؛ رفع شيفرة ضارة؛ أو استخدام الخدمة بطريقة تنتهك حقوق أي طرف ثالث.</p>'
                .'<h2>7. خدمات الأطراف الثالثة</h2><p>قد تتكامل الخدمة مع مزودين خارجيين تختار إعدادهم، مثل بوابات الدفع ومزودي الرسائل عبر واتساب/الرسائل النصية وخدمات تسليم البريد الإلكتروني. يخضع استخدامك لتلك التكاملات أيضًا لشروط ذلك الطرف الثالث، ولا يتحمل دفتري مسؤولية أفعاله أو تقصيره.</p>'
                .'<h2>8. الملكية الفكرية</h2><p>يحتفظ دفتري ومرخِّصوه بجميع الحقوق والملكية والمصلحة في الخدمة نفسها، بما في ذلك برمجياتها وتصميمها وعلامتها التجارية. لا يُنقل إليك بموجب هذه الشروط أي من تلك الملكية الفكرية، عدا الحق المحدود في استخدام الخدمة على النحو المقصود.</p>'
                .'<h2>9. الإنهاء</h2><p>يمكنك إلغاء اشتراكك في أي وقت من إعدادات حسابك. يجوز لنا تعليق وصولك أو إنهاءه إذا خالفت هذه الشروط بشكل جوهري، أو تخلفت عن سداد الرسوم المستحقة، أو إذا طُلب منا ذلك بموجب القانون. عند الإنهاء، ينتهي حقك في استخدام الخدمة، رغم بقاء بعض بنود هذه الشروط سارية (مثل التعامل مع البيانات والمسؤولية والقانون الواجب التطبيق).</p>'
                .'<h2>10. إخلاء المسؤولية وتحديدها</h2><p>تُقدَّم الخدمة "كما هي" و"حسب توفرها". وإلى أقصى حد يسمح به القانون، يخلي دفتري مسؤوليته من جميع الضمانات، الصريحة أو الضمنية، ولا يتحمل مسؤولية الأضرار غير المباشرة أو العرضية أو التبعية الناشئة عن استخدامك للخدمة. تقتصر مسؤوليتنا الإجمالية عن أي مطالبة تتعلق بالخدمة على الرسوم التي دفعتها لنا خلال الاثني عشر شهرًا السابقة للمطالبة.</p>'
                .'<h2>11. القانون الواجب التطبيق</h2><p>تخضع هذه الشروط لأنظمة المملكة العربية السعودية. يخضع أي نزاع ناشئ عن هذه الشروط أو عن استخدامك للخدمة للاختصاص القضائي الحصري للمحاكم المختصة في المملكة.</p>'
                .'<h2>12. التغييرات على هذه الشروط</h2><p>قد نحدّث هذه الشروط من وقت لآخر. سننشر الشروط المحدَّثة في هذه الصفحة مع تاريخ "آخر تحديث" منقّح، وبالنسبة للتغييرات الجوهرية سنقدم إشعارًا إضافيًا (مثل بريد إلكتروني أو إشعار داخل التطبيق). يشكّل استمرار استخدامك للخدمة بعد سريان أي تغيير قبولًا للشروط المعدَّلة.</p>'
                .'<h2>13. تواصل معنا</h2><p>يمكن إرسال الأسئلة حول هذه الشروط عبر صفحة تواصل معنا.</p>',
        ];
    }

    private function privacy(): array
    {
        return [
            'slug' => 'privacy', 'status' => 'published',
            'title_en' => 'Privacy Policy', 'title_ar' => 'سياسة الخصوصية',
            'body_en' => '<p>This Privacy Policy explains how Daftari collects, uses, discloses, and protects personal data when you use our accounting and e-invoicing platform, in line with the Kingdom of Saudi Arabia\'s Personal Data Protection Law (PDPL) and its implementing regulations.</p>'
                .'<h2>Information we collect</h2><p>Account details (name, email, phone number); company information (business name, VAT registration number, Commercial Registration number, address); the invoicing, expense, client, supplier, payroll, and other financial data you enter into the Service; billing and payment details processed by our payment gateway providers; and technical/usage data such as IP address, browser type, device information, and log data collected automatically when you use the Service.</p>'
                .'<h2>How we use your information</h2><p>We use personal data to: provide, operate, and maintain the Service; process your subscription billing; generate and transmit e-invoices to ZATCA on your behalf where you use that feature; send you service-related communications (invoices, receipts, security alerts, support responses); detect and prevent fraud, abuse, and security incidents; and comply with our legal obligations, including tax and accounting record-keeping requirements under Saudi law.</p>'
                .'<h2>How we share information</h2><p>We do not sell personal data. We share it only with: service providers who process data on our behalf under contract (such as cloud hosting, email delivery, payment gateways, and error-monitoring providers), each limited to what they need to perform their function; the Zakat, Tax and Customs Authority (ZATCA), where you use our e-invoicing features, as required for e-invoice clearance and reporting; and other parties where required by law, legal process, or to protect the rights, property, or safety of Daftari, our customers, or others.</p>'
                .'<h2>Data retention</h2><p>We retain personal data and business records for as long as your account is active and thereafter for the period needed to fulfil the purposes described in this policy, including statutory bookkeeping and tax record-retention periods required under Saudi law, after which it is deleted or anonymized.</p>'
                .'<h2>Data security</h2><p>We apply technical and organizational measures designed to protect personal data against unauthorized access, loss, or misuse, including encryption of data in transit, access controls, and regular security review. No method of transmission or storage is completely secure, and we cannot guarantee absolute security.</p>'
                .'<h2>Cross-border data transfer</h2><p>Where personal data is transferred outside the Kingdom of Saudi Arabia (for example, to a cloud infrastructure or service provider located abroad), we take steps to ensure that transfer complies with the PDPL\'s requirements, including using providers that offer an adequate level of protection or are subject to appropriate contractual safeguards.</p>'
                .'<h2>Your rights</h2><p>Subject to the PDPL, you have the right to know what personal data we hold about you, request access to or a copy of it, request correction of inaccurate data, request deletion of your data (subject to our legal retention obligations), and withdraw consent where processing is based on consent. To exercise any of these rights, contact us through our Contact page.</p>'
                .'<h2>Cookies</h2><p>We use cookies as described in our Cookie Policy.</p>'
                .'<h2>Changes to this policy</h2><p>We may update this Privacy Policy from time to time. We will post the updated policy on this page with a revised "Last updated" date, and for material changes we will provide additional notice.</p>'
                .'<h2>Contact us</h2><p>Questions about this Privacy Policy, or requests relating to your personal data, can be sent through our Contact page.</p>',
            'body_ar' => '<p>توضح سياسة الخصوصية هذه كيفية جمع دفتري للبيانات الشخصية واستخدامها والإفصاح عنها وحمايتها عند استخدامك لمنصتنا للمحاسبة والفوترة الإلكترونية، بما يتوافق مع نظام حماية البيانات الشخصية في المملكة العربية السعودية ولوائحه التنفيذية.</p>'
                .'<h2>المعلومات التي نجمعها</h2><p>بيانات الحساب (الاسم والبريد الإلكتروني ورقم الجوال)؛ معلومات المنشأة (الاسم التجاري ورقم التسجيل الضريبي ورقم السجل التجاري والعنوان)؛ بيانات الفواتير والمصروفات والعملاء والموردين والرواتب وغيرها من البيانات المالية التي تُدخلها في الخدمة؛ بيانات الفوترة والدفع التي يعالجها مزودو بوابات الدفع لدينا؛ والبيانات التقنية وبيانات الاستخدام مثل عنوان IP ونوع المتصفح ومعلومات الجهاز وبيانات السجلات التي تُجمع تلقائيًا عند استخدامك للخدمة.</p>'
                .'<h2>كيف نستخدم معلوماتك</h2><p>نستخدم البيانات الشخصية من أجل: تقديم الخدمة وتشغيلها وصيانتها؛ معالجة فوترة اشتراكك؛ إنشاء الفواتير الإلكترونية ونقلها إلى هيئة الزكاة والضريبة والجمارك نيابةً عنك عند استخدامك لتلك الميزة؛ إرسال اتصالات متعلقة بالخدمة إليك (الفواتير والإيصالات وتنبيهات الأمان وردود الدعم)؛ اكتشاف الاحتيال وإساءة الاستخدام والحوادث الأمنية ومنعها؛ والامتثال لالتزاماتنا القانونية، بما في ذلك متطلبات حفظ السجلات المحاسبية والضريبية بموجب النظام السعودي.</p>'
                .'<h2>كيف نشارك المعلومات</h2><p>لا نبيع البيانات الشخصية. نشاركها فقط مع: مزودي الخدمة الذين يعالجون البيانات نيابةً عنا بموجب عقد (مثل الاستضافة السحابية وتسليم البريد الإلكتروني وبوابات الدفع ومزودي مراقبة الأخطاء)، كل منهم مقتصر على ما يحتاجه لأداء وظيفته؛ هيئة الزكاة والضريبة والجمارك، عند استخدامك لميزات الفوترة الإلكترونية، حسب المطلوب لتخليص الفواتير والإبلاغ عنها؛ وأطراف أخرى عند اقتضاء القانون أو الإجراءات القانونية ذلك، أو لحماية حقوق دفتري أو عملائنا أو غيرهم أو ممتلكاتهم أو سلامتهم.</p>'
                .'<h2>الاحتفاظ بالبيانات</h2><p>نحتفظ بالبيانات الشخصية والسجلات التجارية طالما كان حسابك نشطًا، وبعد ذلك للمدة اللازمة لتحقيق الأغراض الموضحة في هذه السياسة، بما في ذلك فترات حفظ السجلات المحاسبية والضريبية النظامية المطلوبة بموجب النظام السعودي، وبعدها تُحذف أو تُجهَّل.</p>'
                .'<h2>أمن البيانات</h2><p>نطبّق تدابير تقنية وتنظيمية مصممة لحماية البيانات الشخصية من الوصول غير المصرح به أو الفقدان أو سوء الاستخدام، بما في ذلك تشفير البيانات أثناء النقل وضوابط الوصول والمراجعة الأمنية الدورية. لا توجد طريقة نقل أو تخزين آمنة تمامًا، ولا يمكننا ضمان أمان مطلق.</p>'
                .'<h2>نقل البيانات عبر الحدود</h2><p>عند نقل البيانات الشخصية خارج المملكة العربية السعودية (مثلاً إلى بنية تحتية سحابية أو مزود خدمة يقع في الخارج)، نتخذ خطوات لضمان امتثال ذلك النقل لمتطلبات نظام حماية البيانات الشخصية، بما في ذلك استخدام مزودين يوفرون مستوى حماية كافيًا أو يخضعون لضمانات تعاقدية مناسبة.</p>'
                .'<h2>حقوقك</h2><p>وفقًا لنظام حماية البيانات الشخصية، يحق لك معرفة البيانات الشخصية التي نحتفظ بها عنك، وطلب الوصول إليها أو الحصول على نسخة منها، وطلب تصحيح البيانات غير الدقيقة، وطلب حذف بياناتك (مع مراعاة التزاماتنا القانونية بالاحتفاظ بها)، وسحب الموافقة عندما تكون المعالجة قائمة على الموافقة. لممارسة أي من هذه الحقوق، تواصل معنا عبر صفحة تواصل معنا.</p>'
                .'<h2>ملفات تعريف الارتباط</h2><p>نستخدم ملفات تعريف الارتباط كما هو موضح في سياسة ملفات تعريف الارتباط الخاصة بنا.</p>'
                .'<h2>التغييرات على هذه السياسة</h2><p>قد نحدّث سياسة الخصوصية هذه من وقت لآخر. سننشر السياسة المحدَّثة في هذه الصفحة مع تاريخ "آخر تحديث" منقّح، وبالنسبة للتغييرات الجوهرية سنقدم إشعارًا إضافيًا.</p>'
                .'<h2>تواصل معنا</h2><p>يمكن إرسال الأسئلة حول سياسة الخصوصية هذه، أو الطلبات المتعلقة ببياناتك الشخصية، عبر صفحة تواصل معنا.</p>',
        ];
    }

    private function cookiePolicy(): array
    {
        return [
            'slug' => 'cookie-policy', 'status' => 'draft',
            'title_en' => 'Cookie Policy', 'title_ar' => 'سياسة ملفات تعريف الارتباط',
            'body_en' => '<p>This Cookie Policy explains how Daftari uses cookies and similar technologies when you visit our website or use the Service.</p>'
                .'<h2>What cookies we use</h2><p>We use essential cookies only — small files required for the Service to function, such as keeping you signed in, remembering your language (Arabic/English) and theme preference, and protecting against cross-site request forgery. We do not use advertising or cross-site tracking cookies, and we do not sell data collected through cookies.</p>'
                .'<h2>Why we need them</h2><p>These cookies are strictly necessary — the Service cannot function without them (for example, staying logged in across pages). Because they are essential, they cannot be switched off individually while continuing to use the Service; disabling cookies entirely in your browser will prevent sign-in from working.</p>'
                .'<h2>Third-party cookies</h2><p>If you use an optional integration you have configured (such as a payment gateway during checkout), that provider may set its own cookies during that step, governed by its own policy — not this one.</p>'
                .'<h2>Managing cookies</h2><p>Most browsers let you view, delete, and block cookies through their settings. Because our cookies are essential to the Service, blocking them will affect your ability to use Daftari.</p>'
                .'<h2>Changes to this policy</h2><p>We may update this Cookie Policy from time to time; we will post the updated policy here with a revised "Last updated" date.</p>'
                .'<h2>Contact us</h2><p>Questions about this Cookie Policy can be sent through our Contact page.</p>',
            'body_ar' => '<p>توضح سياسة ملفات تعريف الارتباط هذه كيفية استخدام دفتري لملفات تعريف الارتباط والتقنيات المشابهة عند زيارتك لموقعنا أو استخدامك للخدمة.</p>'
                .'<h2>ملفات تعريف الارتباط التي نستخدمها</h2><p>نستخدم ملفات تعريف الارتباط الأساسية فقط — ملفات صغيرة ضرورية لعمل الخدمة، مثل إبقائك مسجّلاً للدخول، وتذكّر لغتك المفضلة (عربي/إنجليزي) والمظهر، والحماية من هجمات تزوير الطلبات عبر المواقع. لا نستخدم ملفات تعريف ارتباط إعلانية أو للتتبع عبر المواقع، ولا نبيع البيانات التي تُجمع عبرها.</p>'
                .'<h2>لماذا نحتاجها</h2><p>هذه الملفات ضرورية تمامًا — لا يمكن للخدمة العمل بدونها (مثل البقاء مسجّلاً للدخول بين الصفحات). ولكونها أساسية، لا يمكن إيقافها بشكل فردي مع الاستمرار في استخدام الخدمة؛ وتعطيل ملفات تعريف الارتباط بالكامل في متصفحك سيمنع تسجيل الدخول من العمل.</p>'
                .'<h2>ملفات تعريف ارتباط الأطراف الثالثة</h2><p>إذا استخدمت تكاملاً اختياريًا قمت بإعداده (مثل بوابة دفع أثناء الدفع)، فقد يضع ذلك المزود ملفات تعريف ارتباط خاصة به خلال تلك الخطوة، تخضع لسياسته الخاصة وليس لهذه السياسة.</p>'
                .'<h2>إدارة ملفات تعريف الارتباط</h2><p>تتيح لك معظم المتصفحات عرض ملفات تعريف الارتباط وحذفها وحظرها من خلال إعداداتها. ولأن ملفاتنا أساسية للخدمة، فإن حظرها سيؤثر على قدرتك على استخدام دفتري.</p>'
                .'<h2>التغييرات على هذه السياسة</h2><p>قد نحدّث سياسة ملفات تعريف الارتباط هذه من وقت لآخر؛ وسننشر السياسة المحدَّثة هنا مع تاريخ "آخر تحديث" منقّح.</p>'
                .'<h2>تواصل معنا</h2><p>يمكن إرسال الأسئلة حول هذه السياسة عبر صفحة تواصل معنا.</p>',
        ];
    }

    private function subscriptionAgreement(): array
    {
        return [
            'slug' => 'subscription-agreement', 'status' => 'draft',
            'title_en' => 'SaaS Subscription Agreement', 'title_ar' => 'اتفاقية اشتراك الخدمة',
            'body_en' => '<p>This Subscription Agreement supplements the Terms of Service and governs the commercial terms of your paid subscription to Daftari.</p>'
                .'<h2>1. Plans and billing cycle</h2><p>You may subscribe monthly or annually, at the prices shown on our Pricing page at the time of purchase. Annual subscriptions are billed once for the full year; monthly subscriptions are billed each month on your billing date.</p>'
                .'<h2>2. Auto-renewal</h2><p>Your subscription renews automatically at the end of each billing cycle at the then-current price for your plan, using the payment method on file, unless you cancel before the renewal date from your account\'s Billing settings.</p>'
                .'<h2>3. Free trial</h2><p>New accounts may start with a free trial period, as stated at signup. No payment is collected during the trial. If you add a payment method and do not cancel before the trial ends, your paid subscription begins automatically.</p>'
                .'<h2>4. Upgrades, downgrades, and usage limits</h2><p>Each plan includes specific usage limits (such as invoices per month, number of users, or number of branches) shown on the Pricing page. You may upgrade at any time, effective immediately; you may downgrade at your next renewal, provided your usage fits within the lower plan\'s limits.</p>'
                .'<h2>5. Taxes</h2><p>Fees are exclusive of VAT unless stated otherwise. Where VAT applies under Saudi law, it is added to your invoice, and you will receive a compliant tax invoice for your subscription payment.</p>'
                .'<h2>6. Cancellation and refunds</h2><p>You may cancel at any time; cancellation takes effect at the end of your current paid period, and you retain access until then. See the Refund &amp; Cancellation Policy for details on refund eligibility.</p>'
                .'<h2>7. Suspension for non-payment</h2><p>If a renewal payment fails, we will attempt to notify you and retry the charge. Continued non-payment may result in a grace period followed by suspension, and eventually cancellation, of your subscription, as described in your account\'s Billing settings.</p>'
                .'<h2>8. Changes to this agreement</h2><p>We may update this agreement from time to time, with notice as described in the Terms of Service.</p>',
            'body_ar' => '<p>تكمّل اتفاقية الاشتراك هذه شروط الخدمة وتحكم الشروط التجارية لاشتراكك المدفوع في دفتري.</p>'
                .'<h2>1. الباقات ودورة الفوترة</h2><p>يمكنك الاشتراك شهريًا أو سنويًا، بالأسعار الموضحة في صفحة الأسعار وقت الشراء. تُفوتَر الاشتراكات السنوية دفعة واحدة للسنة كاملة؛ وتُفوتَر الاشتراكات الشهرية كل شهر في تاريخ الفوترة الخاص بك.</p>'
                .'<h2>2. التجديد التلقائي</h2><p>يتجدد اشتراكك تلقائيًا في نهاية كل دورة فوترة بالسعر السائد حينها لباقتك، باستخدام طريقة الدفع المسجلة، ما لم تُلغِه قبل تاريخ التجديد من إعدادات الفوترة في حسابك.</p>'
                .'<h2>3. التجربة المجانية</h2><p>قد تبدأ الحسابات الجديدة بفترة تجربة مجانية، كما هو موضح عند التسجيل. لا تُحصَّل أي مدفوعات خلال التجربة. إذا أضفت طريقة دفع ولم تُلغِ قبل انتهاء التجربة، يبدأ اشتراكك المدفوع تلقائيًا.</p>'
                .'<h2>4. الترقية والتخفيض وحدود الاستخدام</h2><p>تتضمن كل باقة حدود استخدام محددة (مثل عدد الفواتير شهريًا، أو عدد المستخدمين، أو عدد الفروع) موضحة في صفحة الأسعار. يمكنك الترقية في أي وقت وتسري فورًا؛ ويمكنك التخفيض عند تجديدك القادم، شريطة أن يتوافق استخدامك مع حدود الباقة الأقل.</p>'
                .'<h2>5. الضرائب</h2><p>الرسوم لا تشمل ضريبة القيمة المضافة ما لم يُذكر خلاف ذلك. وحيثما تنطبق الضريبة بموجب النظام السعودي، تُضاف إلى فاتورتك، وستحصل على فاتورة ضريبية متوافقة عن دفعة اشتراكك.</p>'
                .'<h2>6. الإلغاء والاسترداد</h2><p>يمكنك الإلغاء في أي وقت؛ ويسري الإلغاء في نهاية فترتك المدفوعة الحالية، وتحتفظ بحق الوصول حتى ذلك الحين. راجع سياسة الاسترداد والإلغاء لتفاصيل أهلية الاسترداد.</p>'
                .'<h2>7. التعليق لعدم السداد</h2><p>في حال فشل دفعة تجديد، سنحاول إشعارك وإعادة محاولة تحصيل الرسوم. قد يؤدي استمرار عدم السداد إلى فترة سماح تليها تعليق، ثم إلغاء اشتراكك في النهاية، كما هو موضح في إعدادات الفوترة في حسابك.</p>'
                .'<h2>8. التغييرات على هذه الاتفاقية</h2><p>قد نحدّث هذه الاتفاقية من وقت لآخر، مع إشعار كما هو موضح في شروط الخدمة.</p>',
        ];
    }

    private function refundPolicy(): array
    {
        return [
            'slug' => 'refund-policy', 'status' => 'draft',
            'title_en' => 'Refund & Cancellation Policy', 'title_ar' => 'سياسة الاسترداد والإلغاء',
            'body_en' => '<p>This policy describes when a subscription payment to Daftari may be refunded, and how cancellation works.</p>'
                .'<h2>General rule</h2><p>Subscription fees are charged in advance for the billing period you selected (monthly or annual) and, except as described below or where required by applicable Saudi consumer-protection law, are non-refundable once the billing period has started.</p>'
                .'<h2>Free trial</h2><p>No payment is collected during a free trial, so nothing is charged unless you continue past the trial without cancelling.</p>'
                .'<h2>Cancelling your subscription</h2><p>You can cancel anytime from your account\'s Billing settings. Cancellation stops future renewal charges; it does not retroactively refund the period you have already paid for, and you keep full access to the Service until that period ends.</p>'
                .'<h2>Billing errors</h2><p>If you believe you were charged in error (for example, a duplicate charge or a charge after you cancelled), contact us through our Contact page with your account details and the payment reference. We will investigate and, where a billing error is confirmed, refund the incorrect charge.</p>'
                .'<h2>Downgrades mid-cycle</h2><p>Moving to a lower-priced plan takes effect at your next renewal rather than immediately, so no partial refund is issued for the plan difference within a period you have already paid for.</p>'
                .'<h2>Service issues</h2><p>If a significant, sustained outage on our side prevents you from using the Service, contact support — we review such cases individually and may issue a partial credit or refund at our discretion.</p>'
                .'<h2>How refunds are issued</h2><p>Approved refunds are returned to the original payment method used for the charge, through our payment gateway provider, and may take a number of business days to appear depending on your bank.</p>',
            'body_ar' => '<p>توضح هذه السياسة متى يمكن استرداد دفعة اشتراك في دفتري، وكيف يعمل الإلغاء.</p>'
                .'<h2>القاعدة العامة</h2><p>تُحصَّل رسوم الاشتراك مقدمًا عن فترة الفوترة التي اخترتها (شهرية أو سنوية)، وباستثناء ما هو موضح أدناه أو ما يقتضيه نظام حماية المستهلك السعودي المعمول به، فهي غير قابلة للاسترداد بمجرد بدء فترة الفوترة.</p>'
                .'<h2>التجربة المجانية</h2><p>لا تُحصَّل أي مدفوعات خلال فترة التجربة المجانية، لذا لا يُحصَّل أي مبلغ ما لم تستمر بعد انتهاء التجربة دون إلغاء.</p>'
                .'<h2>إلغاء اشتراكك</h2><p>يمكنك الإلغاء في أي وقت من إعدادات الفوترة في حسابك. يوقف الإلغاء رسوم التجديد المستقبلية؛ ولا يُسترد بأثر رجعي المبلغ عن الفترة التي دفعت عنها بالفعل، وتحتفظ بحق الوصول الكامل للخدمة حتى نهاية تلك الفترة.</p>'
                .'<h2>أخطاء الفوترة</h2><p>إذا كنت تعتقد أنك حُصِّلت بالخطأ (مثل تحصيل مكرر أو تحصيل بعد إلغائك)، تواصل معنا عبر صفحة تواصل معنا مع تفاصيل حسابك ومرجع الدفع. سنحقق في الأمر، وفي حال تأكيد خطأ الفوترة، سنسترد المبلغ المحصَّل بالخطأ.</p>'
                .'<h2>التخفيض في منتصف الدورة</h2><p>يسري الانتقال إلى باقة أقل سعرًا عند تجديدك القادم وليس فورًا، لذلك لا يُصدر استرداد جزئي عن فرق السعر ضمن فترة دفعت عنها بالفعل.</p>'
                .'<h2>مشكلات الخدمة</h2><p>إذا منعك انقطاع كبير ومستمر من جانبنا من استخدام الخدمة، تواصل مع الدعم — نراجع هذه الحالات فرديًا وقد نصدر رصيدًا جزئيًا أو استردادًا وفق تقديرنا.</p>'
                .'<h2>كيفية إصدار الاسترداد</h2><p>تُعاد المبالغ المستردة المعتمدة إلى طريقة الدفع الأصلية المستخدمة في التحصيل، عبر مزود بوابة الدفع لدينا، وقد تستغرق عدة أيام عمل للظهور حسب بنكك.</p>',
        ];
    }

    private function dataProcessingAgreement(): array
    {
        return [
            'slug' => 'data-processing-agreement', 'status' => 'draft',
            'title_en' => 'Data Processing & Confidentiality Terms', 'title_ar' => 'شروط معالجة البيانات والسرية',
            'body_en' => '<p>These terms describe how Daftari processes the business and personal data you submit to the Service on your behalf, and the confidentiality obligations that apply to it.</p>'
                .'<h2>Roles</h2><p>For the business and customer data you enter into Daftari (your clients\' names, contact details, and transaction records), you act as the data controller and Daftari acts as your data processor, processing that data only to provide the Service and on your instructions (as configured through your use of the Service).</p>'
                .'<h2>Scope of processing</h2><p>Daftari processes the categories of data described in the Privacy Policy — account data, company data, and the invoicing/expense/client/supplier/payroll records you create — solely to operate, secure, and support the Service, including generating and transmitting e-invoices where you use that feature.</p>'
                .'<h2>Confidentiality</h2><p>We treat Your Data as confidential and restrict access to personnel and sub-processors who need it to provide the Service, each bound by confidentiality obligations. We do not disclose Your Data to third parties except as described in the Privacy Policy.</p>'
                .'<h2>Sub-processors</h2><p>We use sub-processors for functions such as cloud hosting, email delivery, payment processing, and error monitoring, each engaged under a contract requiring appropriate data protection safeguards.</p>'
                .'<h2>Security measures</h2><p>We apply the technical and organizational security measures described in the Privacy Policy, including encryption in transit, access controls, and tenant-level data isolation between different companies using the Service.</p>'
                .'<h2>Data breach notification</h2><p>If we become aware of a security incident that compromises Your Data, we will notify you without undue delay and provide the information reasonably available to help you meet your own notification obligations under the PDPL.</p>'
                .'<h2>Return and deletion of data</h2><p>On termination of your account, you may export Your Data; we retain a copy for the period described in the Privacy Policy before deletion, in line with our statutory record-keeping obligations.</p>',
            'body_ar' => '<p>توضح هذه الشروط كيفية معالجة دفتري للبيانات التجارية والشخصية التي تقدمها إلى الخدمة نيابةً عنك، والتزامات السرية المطبقة عليها.</p>'
                .'<h2>الأدوار</h2><p>بالنسبة لبيانات الأعمال والعملاء التي تُدخلها في دفتري (أسماء عملائك وبيانات التواصل معهم وسجلات معاملاتهم)، فأنت تعمل بصفتك متحكم البيانات ويعمل دفتري بصفته معالج البيانات نيابةً عنك، ولا يعالج تلك البيانات إلا لتقديم الخدمة وبناءً على تعليماتك (كما هي مُعدَّة من خلال استخدامك للخدمة).</p>'
                .'<h2>نطاق المعالجة</h2><p>يعالج دفتري فئات البيانات الموضحة في سياسة الخصوصية — بيانات الحساب وبيانات المنشأة وسجلات الفواتير والمصروفات والعملاء والموردين والرواتب التي تنشئها — فقط لتشغيل الخدمة وتأمينها ودعمها، بما في ذلك إنشاء الفواتير الإلكترونية ونقلها عند استخدامك لتلك الميزة.</p>'
                .'<h2>السرية</h2><p>نتعامل مع بياناتك بسرية ونقصر الوصول إليها على الموظفين والجهات المعالجة الفرعية الذين يحتاجونها لتقديم الخدمة، وكل منهم ملزم بالتزامات سرية. لا نفصح عن بياناتك لأطراف ثالثة إلا كما هو موضح في سياسة الخصوصية.</p>'
                .'<h2>الجهات المعالجة الفرعية</h2><p>نستعين بجهات معالجة فرعية لوظائف مثل الاستضافة السحابية وتسليم البريد الإلكتروني ومعالجة المدفوعات ومراقبة الأخطاء، وكل منها متعاقد بموجب شروط تتطلب ضمانات حماية بيانات مناسبة.</p>'
                .'<h2>التدابير الأمنية</h2><p>نطبّق التدابير الأمنية التقنية والتنظيمية الموضحة في سياسة الخصوصية، بما في ذلك التشفير أثناء النقل وضوابط الوصول والعزل الكامل للبيانات بين مختلف المنشآت التي تستخدم الخدمة.</p>'
                .'<h2>الإبلاغ عن اختراق البيانات</h2><p>إذا علمنا بحادث أمني يعرّض بياناتك للخطر، فسنبلغك دون تأخير غير مبرر ونقدم المعلومات المتاحة بشكل معقول لمساعدتك على الوفاء بالتزاماتك الخاصة بالإبلاغ بموجب نظام حماية البيانات الشخصية.</p>'
                .'<h2>إعادة البيانات وحذفها</h2><p>عند إنهاء حسابك، يمكنك تصدير بياناتك؛ ونحتفظ بنسخة منها للمدة الموضحة في سياسة الخصوصية قبل حذفها، بما يتماشى مع التزاماتنا النظامية بحفظ السجلات.</p>',
        ];
    }

    private function customerResponsibilities(): array
    {
        return [
            'slug' => 'customer-responsibilities', 'status' => 'draft',
            'title_en' => 'Customer Responsibilities', 'title_ar' => 'مسؤوليات العميل',
            'body_en' => '<p>Daftari is a software tool that helps you run your accounting, invoicing, payroll, and e-invoicing workflows. Certain responsibilities always remain yours as the account holder and business owner.</p>'
                .'<h2>Accuracy of your data</h2><p>You are responsible for the accuracy of the VAT, company, employee, and transaction information you enter into the Service. Daftari calculates and formats based on what you enter — it cannot verify facts about your business that only you know.</p>'
                .'<h2>Your own tax, payroll, and legal compliance</h2><p>You remain solely responsible for your business\'s compliance with Saudi tax law, ZATCA e-invoicing requirements, GOSI and Wage Protection System (WPS) obligations, labor law, and any other applicable regulation. Daftari provides tools designed to support these workflows; it does not replace a qualified accountant, tax advisor, or lawyer, and using the Service does not itself guarantee your compliance — see the ZATCA &amp; Customer Responsibility Disclaimer.</p>'
                .'<h2>Account security</h2><p>You are responsible for who you invite to your account, the permissions you grant them, and keeping your own and your team\'s login credentials secure.</p>'
                .'<h2>Your registered business activity</h2><p>You are responsible for ensuring your own business is legally permitted to purchase and use software of this kind under your Commercial Registration and any other licenses that apply to you — this is a question for your own business/legal advisor, not something Daftari verifies on your behalf.</p>'
                .'<h2>Backups and exports</h2><p>While we maintain the Service and its backups, we recommend periodically exporting your key records (invoices, reports) for your own records, particularly before making major changes to your account.</p>'
                .'<h2>Third-party integrations you configure</h2><p>If you connect a payment gateway, messaging provider, or other third-party service, you are responsible for that provider\'s own terms and for any data you choose to share with them.</p>',
            'body_ar' => '<p>دفتري أداة برمجية تساعدك على إدارة سير عمل المحاسبة والفوترة والرواتب والفوترة الإلكترونية. تبقى بعض المسؤوليات دائمًا على عاتقك بصفتك صاحب الحساب وصاحب المنشأة.</p>'
                .'<h2>دقة بياناتك</h2><p>أنت مسؤول عن دقة معلومات ضريبة القيمة المضافة والمنشأة والموظفين والمعاملات التي تُدخلها في الخدمة. يحسب دفتري وينسّق بناءً على ما تُدخله — ولا يمكنه التحقق من حقائق عن منشأتك لا يعرفها سواك.</p>'
                .'<h2>امتثالك الضريبي وامتثال الرواتب والامتثال القانوني</h2><p>تظل وحدك مسؤولاً عن امتثال منشأتك لنظام الضريبة السعودي، ومتطلبات الفوترة الإلكترونية لهيئة الزكاة والضريبة والجمارك، والتزامات التأمينات الاجتماعية ونظام حماية الأجور، ونظام العمل، وأي أنظمة أخرى معمول بها. يوفر دفتري أدوات مصممة لدعم سير هذه الأعمال؛ ولا يحل محل محاسب أو مستشار ضريبي أو محامٍ مؤهل، ولا يضمن استخدام الخدمة في حد ذاته امتثالك — راجع إخلاء مسؤولية الهيئة ومسؤولية العميل.</p>'
                .'<h2>أمن الحساب</h2><p>أنت مسؤول عمّن تدعوه إلى حسابك، والصلاحيات التي تمنحها له، والحفاظ على سرية بيانات اعتماد تسجيل الدخول الخاصة بك وبفريقك.</p>'
                .'<h2>نشاطك التجاري المسجل</h2><p>أنت مسؤول عن التأكد من أن منشأتك مصرح لها نظاميًا بشراء برمجيات من هذا النوع واستخدامها بموجب سجلك التجاري وأي تراخيص أخرى معمول بها لديك — وهذا سؤال يخص مستشارك التجاري أو القانوني الخاص، وليس أمرًا يتحقق منه دفتري نيابةً عنك.</p>'
                .'<h2>النسخ الاحتياطي والتصدير</h2><p>رغم صيانتنا للخدمة ونسخها الاحتياطية، نوصي بتصدير سجلاتك الرئيسية دوريًا (الفواتير والتقارير) لسجلاتك الخاصة، خصوصًا قبل إجراء تغييرات كبيرة على حسابك.</p>'
                .'<h2>تكاملات الأطراف الثالثة التي تُعدّها</h2><p>إذا ربطت بوابة دفع أو مزود رسائل أو أي خدمة خارجية أخرى، فأنت مسؤول عن شروط ذلك المزود وعن أي بيانات تختار مشاركتها معه.</p>',
        ];
    }

    private function zatcaDisclaimer(): array
    {
        return [
            'slug' => 'zatca-disclaimer', 'status' => 'draft',
            'title_en' => 'ZATCA & Customer Responsibility Disclaimer', 'title_ar' => 'إخلاء مسؤولية الهيئة ومسؤولية العميل',
            'body_en' => '<p>This page clarifies exactly what Daftari does and does not claim regarding Zakat, Tax and Customs Authority (ZATCA) e-invoicing compliance, so you can make an informed decision about your own regulatory obligations.</p>'
                .'<h2>What Daftari provides</h2><p>Daftari includes features designed to support ZATCA\'s e-invoicing requirements: generating tax invoices with the required fields, producing a scannable QR code, generating the XML structure and digital signature ZATCA\'s technical specification calls for, and — where you connect your own ZATCA credentials — transmitting invoices for clearance or reporting through ZATCA\'s API.</p>'
                .'<h2>What we do not claim</h2><p>We do not claim that Daftari is "ZATCA-certified," "officially approved by ZATCA," or that using it guarantees your business\'s compliance. ZATCA does not certify third-party software providers in that sense; it defines a technical specification that software can be built to support. Any statement elsewhere on our site or marketing describing a feature as "ZATCA-compliant" refers to that feature producing output built to ZATCA\'s published technical format — not to Daftari itself holding a government-issued certification, unless we explicitly state we have obtained one for a stated period.</p>'
                .'<h2>Whose responsibility it is</h2><p>Determining which ZATCA phase and rollout wave applies to your business, registering your own ZATCA credentials, verifying the accuracy of every invoice you issue, and meeting your filing deadlines are your responsibility as the taxpayer. We recommend confirming your specific obligations with ZATCA\'s own published guidance or a licensed Saudi tax advisor.</p>'
                .'<h2>If ZATCA rejects an invoice</h2><p>ZATCA may reject a submission for reasons related to the data you entered (for example, an incorrect VAT number or a validation rule specific to your business type). Daftari surfaces the rejection reason returned by ZATCA so you can correct and resubmit; resolving the underlying issue is your responsibility.</p>'
                .'<h2>Keeping this disclaimer current</h2><p>If our understanding of ZATCA\'s requirements or our own compliance status changes, we will update this page rather than leave an outdated claim in place.</p>',
            'body_ar' => '<p>توضح هذه الصفحة بدقة ما يدّعيه دفتري وما لا يدّعيه بشأن الامتثال لمتطلبات الفوترة الإلكترونية لهيئة الزكاة والضريبة والجمارك، حتى تتمكن من اتخاذ قرار مستنير بشأن التزاماتك التنظيمية الخاصة.</p>'
                .'<h2>ما يقدمه دفتري</h2><p>يتضمن دفتري ميزات مصممة لدعم متطلبات الفوترة الإلكترونية للهيئة: إنشاء فواتير ضريبية بالحقول المطلوبة، وإصدار رمز QR قابل للمسح، وإنشاء بنية XML والتوقيع الرقمي وفق المواصفات التقنية للهيئة، وحيثما تربط بيانات اعتماد الهيئة الخاصة بك — نقل الفواتير للتخليص أو الإبلاغ عبر واجهة برمجة تطبيقات الهيئة.</p>'
                .'<h2>ما لا ندّعيه</h2><p>لا ندّعي أن دفتري "معتمد من الهيئة" أو "مُوافق عليه رسميًا من الهيئة"، أو أن استخدامه يضمن امتثال منشأتك. لا تعتمد الهيئة مزودي البرمجيات الخارجيين بهذا المعنى؛ بل تحدد مواصفة تقنية يمكن بناء البرمجيات لدعمها. أي عبارة في موقعنا أو موادنا التسويقية تصف ميزة بأنها "متوافقة مع الهيئة" تشير إلى أن تلك الميزة تُنتج مخرجات مبنية وفق الصيغة التقنية المنشورة من الهيئة — وليس إلى حصول دفتري نفسه على شهادة حكومية، ما لم نذكر صراحةً أننا حصلنا على واحدة لفترة محددة.</p>'
                .'<h2>على من تقع المسؤولية</h2><p>تحديد مرحلة وموجة التطبيق التي تنطبق على منشأتك، وتسجيل بيانات اعتماد الهيئة الخاصة بك، والتحقق من دقة كل فاتورة تصدرها، والالتزام بمواعيد تقديم إقراراتك، كلها مسؤوليتك بصفتك المكلَّف. نوصي بالتأكد من التزاماتك المحددة عبر إرشادات الهيئة المنشورة أو مستشار ضريبي سعودي مرخّص.</p>'
                .'<h2>في حال رفض الهيئة لفاتورة</h2><p>قد ترفض الهيئة تقديمًا لأسباب متعلقة بالبيانات التي أدخلتها (مثل رقم ضريبي غير صحيح أو قاعدة تحقق خاصة بنوع نشاطك). يعرض دفتري سبب الرفض الذي تُرجعه الهيئة حتى تتمكن من التصحيح وإعادة التقديم؛ ومعالجة المشكلة الأساسية تقع على عاتقك.</p>'
                .'<h2>تحديث هذا الإخلاء</h2><p>إذا تغيّر فهمنا لمتطلبات الهيئة أو تغيّرت حالة امتثالنا الخاصة، فسنحدّث هذه الصفحة بدلاً من ترك ادّعاء قديم قائمًا.</p>',
        ];
    }
}
