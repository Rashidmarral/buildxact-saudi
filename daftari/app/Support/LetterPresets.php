<?php

namespace App\Support;

/**
 * Starter bilingual content for the Letters & Agreements generator (see
 * CompanyLetter/CompanyLetterController) — every block here is only a
 * starting point: the user can add, edit, or remove any paragraph once
 * the letter is created. This is boilerplate drafted to reflect common
 * Saudi commercial practice (bilingual precedence, KSA governing law,
 * ZATCA VAT treatment, Istimara transfer for equipment) — not certified
 * legal advice; a company should still have its own counsel review a
 * template before relying on it for a real dispute.
 */
class LetterPresets
{
    public const KINDS = [
        'machinery_sale_agreement',
        'machinery_purchase_agreement',
        'machinery_rental_agreement',
        'work_handover_letter',
        'custom',
    ];

    public static function label(string $kind): string
    {
        return match ($kind) {
            'machinery_sale_agreement' => __('Machinery Sale Agreement'),
            'machinery_purchase_agreement' => __('Machinery Purchase Agreement'),
            'machinery_rental_agreement' => __('Machinery Rental Agreement'),
            'work_handover_letter' => __('Work Handover Letter'),
            default => __('Custom Letter'),
        };
    }

    /**
     * @return array{title: string, party_a_role: string, party_b_role: string, content: array<int, array{text_en: string, text_ar: string, is_heading: bool}>}
     */
    public static function blueprint(string $kind): array
    {
        return match ($kind) {
            'machinery_sale_agreement' => self::saleAgreement(),
            'machinery_purchase_agreement' => self::purchaseAgreement(),
            'machinery_rental_agreement' => self::rentalAgreement(),
            'work_handover_letter' => self::workHandoverLetter(),
            default => self::blank(),
        };
    }

    private static function block(string $en, string $ar, bool $heading = false): array
    {
        return ['text_en' => $en, 'text_ar' => $ar, 'is_heading' => $heading];
    }

    private static function saleAgreement(): array
    {
        return [
            'title' => __('Machinery Sale Agreement'),
            'party_a_role' => 'Seller',
            'party_b_role' => 'Buyer',
            'content' => [
                self::block(
                    'This Sale Agreement ("Agreement") is entered into on the date below between the Seller and the Buyer named above, for the sale of the equipment described below.',
                    'أُبرم اتفاق البيع هذا ("الاتفاق") بتاريخه أدناه بين البائع والمشتري المذكورين أعلاه، لبيع المعدة الموصوفة أدناه.'
                ),
                self::block('1. Equipment Description', '1. وصف المعدة', true),
                self::block(
                    'Make/Model: ____________  Serial/Chassis No.: ____________  Year: ____________  Condition: sold "as-is", inspected and accepted by the Buyer prior to signing.',
                    'الصانع/الطراز: ____________  رقم التسلسل/الشاسيه: ____________  سنة الصنع: ____________  الحالة: تُباع "كما هي"، وقد تم فحصها وقبولها من المشتري قبل التوقيع.'
                ),
                self::block('2. Price and Payment', '2. الثمن والدفع', true),
                self::block(
                    'The total sale price is stated on this agreement, [inclusive/exclusive] of 15% Value Added Tax in accordance with the regulations of the Zakat, Tax and Customs Authority (ZATCA). Payment terms: ____________.',
                    'إجمالي ثمن البيع مذكور في هذا الاتفاق، [شامل/غير شامل] ضريبة القيمة المضافة بنسبة 15% وفقًا لأنظمة هيئة الزكاة والضريبة والجمارك (ZATCA). شروط السداد: ____________.'
                ),
                self::block('3. Transfer of Title and Risk', '3. انتقال الملكية والمخاطر', true),
                self::block(
                    'Title and risk in the equipment pass to the Buyer upon full payment and delivery. The Seller declares the equipment is free of any lien, mortgage, or third-party claim as of the date of this agreement.',
                    'تنتقل ملكية المعدة ومخاطرها إلى المشتري عند سداد كامل الثمن والتسليم. يقر البائع بأن المعدة خالية من أي رهن أو حجز أو مطالبة من طرف ثالث حتى تاريخ هذا الاتفاق.'
                ),
                self::block('4. Registration Transfer', '4. نقل الاستمارة', true),
                self::block(
                    'Where the equipment is subject to vehicle/equipment registration (Istimara), the Seller shall cooperate to complete the transfer of registration to the Buyer\'s name within a reasonable period following payment.',
                    'في حال كانت المعدة خاضعة لتسجيل مركبات/معدات (استمارة)، يلتزم البائع بالتعاون على إتمام نقل الاستمارة باسم المشتري خلال مدة معقولة بعد السداد.'
                ),
                self::block('5. Warranty', '5. الضمان', true),
                self::block(
                    'Unless otherwise stated in writing, the equipment is sold without warranty beyond the condition disclosed and accepted at the time of sale.',
                    'ما لم يُنص خطيًا على خلاف ذلك، تُباع المعدة دون أي ضمان يتجاوز الحالة المُفصح عنها والمقبولة وقت البيع.'
                ),
                self::block('6. Governing Law and Disputes', '6. القانون الواجب التطبيق وتسوية النزاعات', true),
                self::block(
                    'This Agreement is governed by the laws and regulations of the Kingdom of Saudi Arabia. Any dispute shall first be settled amicably; failing that, it shall be referred to the competent courts of the Kingdom of Saudi Arabia.',
                    'يخضع هذا الاتفاق لأنظمة وقوانين المملكة العربية السعودية. تتم تسوية أي نزاع وديًا أولًا، وفي حال تعذر ذلك يُحال إلى المحاكم المختصة في المملكة العربية السعودية.'
                ),
                self::block(
                    'This Agreement is executed in both Arabic and English; in the event of any discrepancy between the two texts, the Arabic text shall prevail.',
                    'حُرر هذا الاتفاق باللغتين العربية والإنجليزية، وفي حال وجود أي تعارض بين النصين يُعتد بالنص العربي.'
                ),
            ],
        ];
    }

    private static function purchaseAgreement(): array
    {
        return [
            'title' => __('Machinery Purchase Agreement'),
            'party_a_role' => 'Buyer',
            'party_b_role' => 'Seller',
            'content' => [
                self::block(
                    'This Purchase Agreement ("Agreement") is entered into on the date below between the Buyer and the Seller named above, for the purchase of the equipment described below.',
                    'أُبرم اتفاق الشراء هذا ("الاتفاق") بتاريخه أدناه بين المشتري والبائع المذكورين أعلاه، لشراء المعدة الموصوفة أدناه.'
                ),
                self::block('1. Equipment Description', '1. وصف المعدة', true),
                self::block(
                    'Make/Model: ____________  Serial/Chassis No.: ____________  Year: ____________  Condition: ____________.',
                    'الصانع/الطراز: ____________  رقم التسلسل/الشاسيه: ____________  سنة الصنع: ____________  الحالة: ____________.'
                ),
                self::block('2. Price and Payment', '2. الثمن والدفع', true),
                self::block(
                    'The total purchase price is stated on this agreement, [inclusive/exclusive] of 15% Value Added Tax in accordance with ZATCA regulations. Payment terms: ____________. Delivery date/location: ____________.',
                    'إجمالي ثمن الشراء مذكور في هذا الاتفاق، [شامل/غير شامل] ضريبة القيمة المضافة بنسبة 15% وفقًا لأنظمة هيئة الزكاة والضريبة والجمارك. شروط السداد: ____________. تاريخ ومكان التسليم: ____________.'
                ),
                self::block('3. Seller\'s Declarations', '3. إقرارات البائع', true),
                self::block(
                    'The Seller declares the equipment is free of any lien, mortgage, or third-party claim, and shall cooperate to complete any required registration (Istimara) transfer to the Buyer\'s name.',
                    'يقر البائع بأن المعدة خالية من أي رهن أو حجز أو مطالبة من طرف ثالث، ويلتزم بالتعاون على إتمام نقل أي تسجيل مطلوب (استمارة) باسم المشتري.'
                ),
                self::block('4. Inspection', '4. المعاينة', true),
                self::block(
                    'The Buyer has inspected the equipment prior to signing and accepts its condition as disclosed, unless a warranty period is separately agreed in writing.',
                    'قام المشتري بمعاينة المعدة قبل التوقيع ويقبل حالتها كما تم الإفصاح عنها، ما لم يُتفق كتابيًا على فترة ضمان منفصلة.'
                ),
                self::block('5. Governing Law and Disputes', '5. القانون الواجب التطبيق وتسوية النزاعات', true),
                self::block(
                    'This Agreement is governed by the laws and regulations of the Kingdom of Saudi Arabia. Any dispute shall first be settled amicably; failing that, it shall be referred to the competent courts of the Kingdom of Saudi Arabia.',
                    'يخضع هذا الاتفاق لأنظمة وقوانين المملكة العربية السعودية. تتم تسوية أي نزاع وديًا أولًا، وفي حال تعذر ذلك يُحال إلى المحاكم المختصة في المملكة العربية السعودية.'
                ),
                self::block(
                    'This Agreement is executed in both Arabic and English; in the event of any discrepancy between the two texts, the Arabic text shall prevail.',
                    'حُرر هذا الاتفاق باللغتين العربية والإنجليزية، وفي حال وجود أي تعارض بين النصين يُعتد بالنص العربي.'
                ),
            ],
        ];
    }

    private static function rentalAgreement(): array
    {
        return [
            'title' => __('Machinery Rental Agreement'),
            'party_a_role' => 'Owner',
            'party_b_role' => 'Renter',
            'content' => [
                self::block(
                    'This Equipment Rental Agreement ("Agreement") is entered into on the date below between the Owner and the Renter named above, for the rental of the equipment described below.',
                    'أُبرم اتفاق تأجير المعدة هذا ("الاتفاق") بتاريخه أدناه بين المالك والمستأجر المذكورين أعلاه، لتأجير المعدة الموصوفة أدناه.'
                ),
                self::block('1. Equipment and Rental Period', '1. المعدة ومدة الإيجار', true),
                self::block(
                    'Make/Model: ____________  Serial/Chassis No.: ____________  Rental period: from ____________ to ____________ (or ongoing until returned).',
                    'الصانع/الطراز: ____________  رقم التسلسل/الشاسيه: ____________  مدة الإيجار: من ____________ إلى ____________ (أو مستمرة حتى الإرجاع).'
                ),
                self::block('2. Rate and Payment', '2. الأجرة والسداد', true),
                self::block(
                    'The rental rate and payment schedule are stated on this agreement, [inclusive/exclusive] of 15% Value Added Tax per ZATCA regulations. A security deposit of ____________ is [required/not required], refundable subject to the equipment\'s return condition.',
                    'أجرة الإيجار وجدول السداد مذكوران في هذا الاتفاق، [شاملة/غير شاملة] ضريبة القيمة المضافة بنسبة 15% وفقًا لأنظمة هيئة الزكاة والضريبة والجمارك. مبلغ تأمين قدره ____________ [مطلوب/غير مطلوب]، ويُرد وفقًا لحالة إرجاع المعدة.'
                ),
                self::block('3. Use and Operation', '3. الاستخدام والتشغيل', true),
                self::block(
                    'The equipment shall be used only for its intended purpose and at the site agreed between the parties. [An operator is provided by the Owner / No operator is provided; the Renter is responsible for a qualified operator].',
                    'تُستخدم المعدة فقط للغرض المخصص لها وفي الموقع المتفق عليه بين الطرفين. [يوفر المالك مشغلًا / لا يوفر المالك مشغلًا، ويتحمل المستأجر مسؤولية توفير مشغل مؤهل].'
                ),
                self::block('4. Fuel, Maintenance, and Insurance', '4. الوقود والصيانة والتأمين', true),
                self::block(
                    'Responsibility for fuel is as stated on this agreement. Routine maintenance during the rental period is the responsibility of ____________. Each party shall maintain insurance covering its own liability as required by law.',
                    'تكون مسؤولية الوقود كما هو مذكور في هذا الاتفاق. تقع مسؤولية الصيانة الدورية خلال مدة الإيجار على عاتق ____________. يلتزم كل طرف بالحفاظ على تأمين يغطي مسؤوليته وفق ما يقتضيه النظام.'
                ),
                self::block('5. Liability and Damage', '5. المسؤولية والأضرار', true),
                self::block(
                    'The Renter is responsible for any damage to the equipment beyond normal wear and tear occurring during the rental period, and for any loss, injury, or third-party claim arising from its use while in the Renter\'s custody.',
                    'يتحمل المستأجر مسؤولية أي ضرر يلحق بالمعدة يتجاوز الاستهلاك العادي خلال مدة الإيجار، وأي خسارة أو إصابة أو مطالبة من طرف ثالث تنشأ عن استخدامها أثناء وجودها في عهدته.'
                ),
                self::block('6. Return Condition', '6. حالة الإرجاع', true),
                self::block(
                    'The equipment shall be returned in the same condition as delivered, normal wear and tear excepted. A condition report shall be completed jointly at delivery and at return.',
                    'تُعاد المعدة بنفس الحالة التي كانت عليها عند التسليم، باستثناء الاستهلاك العادي. يُعد تقرير حالة مشترك عند التسليم وعند الإرجاع.'
                ),
                self::block('7. Early Termination and Force Majeure', '7. الإنهاء المبكر والقوة القاهرة', true),
                self::block(
                    'Either party may terminate this Agreement early upon written notice as agreed between the parties. Neither party is liable for delay or failure to perform caused by events beyond its reasonable control.',
                    'يجوز لأي من الطرفين إنهاء هذا الاتفاق مبكرًا بإشعار كتابي وفق ما يتفق عليه الطرفان. لا يتحمل أي طرف المسؤولية عن أي تأخير أو إخلال ناتج عن ظروف خارجة عن إرادته المعقولة.'
                ),
                self::block('8. Governing Law and Disputes', '8. القانون الواجب التطبيق وتسوية النزاعات', true),
                self::block(
                    'This Agreement is governed by the laws and regulations of the Kingdom of Saudi Arabia. Any dispute shall first be settled amicably; failing that, it shall be referred to the competent courts of the Kingdom of Saudi Arabia.',
                    'يخضع هذا الاتفاق لأنظمة وقوانين المملكة العربية السعودية. تتم تسوية أي نزاع وديًا أولًا، وفي حال تعذر ذلك يُحال إلى المحاكم المختصة في المملكة العربية السعودية.'
                ),
                self::block(
                    'This Agreement is executed in both Arabic and English; in the event of any discrepancy between the two texts, the Arabic text shall prevail.',
                    'حُرر هذا الاتفاق باللغتين العربية والإنجليزية، وفي حال وجود أي تعارض بين النصين يُعتد بالنص العربي.'
                ),
            ],
        ];
    }

    private static function workHandoverLetter(): array
    {
        return [
            'title' => __('Work Handover Letter'),
            'party_a_role' => 'Contractor',
            'party_b_role' => 'Client',
            'content' => [
                self::block(
                    'With reference to our agreement for the project named above, we hereby submit the completed works described below for joint inspection and handover.',
                    'بالإشارة إلى اتفاقنا الخاص بالمشروع المذكور أعلاه، نتقدم إليكم بطلب معاينة واستلام الأعمال المنجزة الموصوفة أدناه بشكل مشترك.'
                ),
                self::block(
                    'The work area/scope has been completed and prepared for the next stage, subject to confirmation of line, level, quality, and general condition during the joint inspection.',
                    'تم إنجاز نطاق/منطقة العمل وتجهيزها للمرحلة التالية، وذلك بعد التحقق خلال المعاينة المشتركة من المحاور والمناسيب والجودة والحالة العامة.'
                ),
                self::block(
                    'We kindly request your representative to inspect the completed works and confirm acceptance. Upon approval, work on the next stage will commence.',
                    'نرجو من ممثلكم معاينة الأعمال المنجزة واعتماد استلامها. وبعد صدور الموافقة، سيتم مباشرة العمل في المرحلة التالية.'
                ),
                self::block(
                    'Any observations raised during inspection will be corrected promptly. Unexpected or unsuitable conditions will be reported for joint review before further work.',
                    'سيتم تنفيذ أي ملاحظات تنتج عن المعاينة فورًا. كما سيتم إبلاغكم بأي ظروف غير متوقعة أو غير مناسبة لمراجعتها بشكل مشترك قبل استكمال الأعمال.'
                ),
                self::block(
                    'Please confirm acceptance by signing below or issuing written approval so that the next stage can commence without delay.',
                    'نأمل تأكيد الاستلام بالتوقيع أدناه أو إصدار موافقة خطية للبدء في المرحلة التالية دون تأخير.'
                ),
            ],
        ];
    }

    private static function blank(): array
    {
        return [
            'title' => __('Letter'),
            'party_a_role' => 'Company',
            'party_b_role' => 'Client',
            'content' => [
                self::block('', ''),
            ],
        ];
    }
}
