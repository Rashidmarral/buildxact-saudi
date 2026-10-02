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
        'machinery_hire_in_agreement',
        'work_handover_letter',
        'custom',
    ];

    public static function label(string $kind): string
    {
        return match ($kind) {
            'machinery_sale_agreement' => __('Machinery Sale Agreement'),
            'machinery_purchase_agreement' => __('Machinery Purchase Agreement'),
            'machinery_rental_agreement' => __('Machinery Rental Agreement'),
            'machinery_hire_in_agreement' => __('Equipment Hire Agreement'),
            'work_handover_letter' => __('Work Handover Letter'),
            default => __('Custom Letter'),
        };
    }

    /**
     * @return array{title: string, title_ar: string, party_a_role: string, party_b_role: string, content: array<int, array{text_en: string, text_ar: string, is_heading: bool}>}
     */
    public static function blueprint(string $kind): array
    {
        return match ($kind) {
            'machinery_sale_agreement' => self::saleAgreement(),
            'machinery_purchase_agreement' => self::purchaseAgreement(),
            'machinery_rental_agreement' => self::rentalAgreement(),
            'machinery_hire_in_agreement' => self::hireInAgreement(),
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
            'title' => 'Machinery Sale Agreement',
            'title_ar' => 'اتفاقية بيع آلية',
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
            'title' => 'Machinery Purchase Agreement',
            'title_ar' => 'اتفاقية شراء آلية',
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
            'title' => 'Machinery Rental Agreement',
            'title_ar' => 'اتفاقية تأجير آلية',
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

    /**
     * The reverse of rentalAgreement(): here the COMPANY is the hirer
     * (First Party), not the owner — modeled directly on a real bilingual
     * "MC1 & RC2 Vehicle/Equipment Agreement" where the company hires a
     * spray-tanker + driver from an external supplier at a per-m² rate,
     * generalized beyond that one equipment type.
     */
    private static function hireInAgreement(): array
    {
        return [
            'title' => 'Equipment Hire Agreement',
            'title_ar' => 'اتفاقية استئجار معدات',
            'party_a_role' => 'First Party (Hirer)',
            'party_b_role' => 'Second Party (Equipment Supplier)',
            'content' => [
                self::block(
                    'This Equipment Hire Agreement ("Agreement") is entered into on the date below between the First Party and the Second Party named above, under which the Second Party shall provide the equipment and services described below to the First Party.',
                    'أُبرمت اتفاقية استئجار المعدات هذه ("الاتفاق") بتاريخه أدناه بين الطرف الأول والطرف الثاني المذكورين أعلاه، يقوم الطرف الثاني بموجبها بتوفير المعدة والخدمات الموصوفة أدناه للطرف الأول.'
                ),
                self::block('1. Provision of Equipment and Operator', '1. توفير المعدة والمشغل', true),
                self::block(
                    'The Second Party shall provide the equipment described in this agreement together with a qualified, licensed driver/operator for its operation, for the duration stated below.',
                    'يلتزم الطرف الثاني بتوفير المعدة الموصوفة في هذا الاتفاق مع مشغل/سائق مؤهل ومرخص لتشغيلها، طوال المدة المحددة أدناه.'
                ),
                self::block('2. Fuel, Maintenance, and Operating Costs', '2. الوقود والصيانة وتكاليف التشغيل', true),
                self::block(
                    'Unless otherwise stated in this agreement, the Second Party shall bear all costs of diesel/fuel, routine maintenance, repairs, spare parts, tyres, oils and lubricants, and all other costs necessary to keep the equipment operating.',
                    'ما لم يُنص على خلاف ذلك في هذا الاتفاق، يتحمل الطرف الثاني جميع تكاليف الديزل/الوقود والصيانة الدورية والإصلاحات وقطع الغيار والإطارات والزيوت ومواد التزليق وجميع التكاليف الأخرى اللازمة لاستمرار تشغيل المعدة.'
                ),
                self::block('3. First Party\'s Obligations', '3. التزامات الطرف الأول', true),
                self::block(
                    'The First Party shall provide only the materials and/or site access specified in this agreement and shall bear their cost; the First Party bears no responsibility for the equipment\'s fuel, maintenance, or operating costs.',
                    'يلتزم الطرف الأول بتوفير المواد و/أو الوصول إلى الموقع المحدد في هذا الاتفاق فقط ويتحمل تكلفتها؛ ولا يتحمل الطرف الأول أي مسؤولية عن وقود المعدة أو صيانتها أو تكاليف تشغيلها.'
                ),
                self::block('4. Availability of Equipment', '4. توفر المعدة', true),
                self::block(
                    'The Second Party shall make the equipment and operator available whenever requested by the First Party for the duration of this agreement, and shall not refuse, delay, or withdraw the equipment without the First Party\'s prior written approval.',
                    'يلتزم الطرف الثاني بتوفير المعدة والمشغل عند طلب الطرف الأول طوال مدة هذا الاتفاق، ولا يجوز له رفض أو تأخير أو سحب المعدة دون موافقة الطرف الأول الخطية المسبقة.'
                ),
                self::block('5. No Substitution Without Consent', '5. عدم الاستبدال دون موافقة', true),
                self::block(
                    'The equipment and operator approved under this agreement may not be substituted for different equipment or a different operator without the First Party\'s prior consent.',
                    'لا يجوز استبدال المعدة أو المشغل المعتمدين بموجب هذا الاتفاق بمعدة أو مشغل آخر دون موافقة الطرف الأول المسبقة.'
                ),
                self::block('6. Liability for Loss or Damage to Materials', '6. المسؤولية عن فقد أو تلف المواد', true),
                self::block(
                    'The Second Party shall bear the cost of any loss, leakage, spillage, or damage to the First Party\'s materials occurring during transport or handling by the equipment while in the Second Party\'s custody or operation.',
                    'يتحمل الطرف الثاني تكلفة أي فقد أو تسرب أو انسكاب أو تلف يلحق بمواد الطرف الأول يحدث أثناء النقل أو المناولة بواسطة المعدة طالما كانت في عهدة أو تحت تشغيل الطرف الثاني.'
                ),
                self::block('7. Rate and Payment', '7. الأجرة والسداد', true),
                self::block(
                    'The agreed rate is stated on this agreement and is inclusive of the equipment, operator, fuel, and maintenance unless stated otherwise, [inclusive/exclusive] of 15% Value Added Tax per ZATCA regulations. Payment shall be made against measured/certified work or the agreed billing period.',
                    'الأجرة المتفق عليها مذكورة في هذا الاتفاق وتشمل المعدة والمشغل والوقود والصيانة ما لم يُنص على خلاف ذلك، [شاملة/غير شاملة] ضريبة القيمة المضافة بنسبة 15% وفقًا لأنظمة هيئة الزكاة والضريبة والجمارك. يتم السداد وفق الأعمال المقاسة/المعتمدة أو فترة الفوترة المتفق عليها.'
                ),
                self::block(
                    'This Agreement is governed by the laws and regulations of the Kingdom of Saudi Arabia. Any dispute shall first be settled amicably; failing that, it shall be referred to the competent courts of the Kingdom of Saudi Arabia. This Agreement is executed in both Arabic and English; in the event of any discrepancy, the Arabic text shall prevail.',
                    'يخضع هذا الاتفاق لأنظمة وقوانين المملكة العربية السعودية. تتم تسوية أي نزاع وديًا أولًا، وفي حال تعذر ذلك يُحال إلى المحاكم المختصة في المملكة العربية السعودية. حُرر هذا الاتفاق باللغتين العربية والإنجليزية، وفي حال وجود أي تعارض يُعتد بالنص العربي.'
                ),
            ],
        ];
    }

    private static function workHandoverLetter(): array
    {
        return [
            'title' => 'Work Handover Letter',
            'title_ar' => 'خطاب تسليم أعمال',
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
            'title' => 'Letter',
            'title_ar' => 'خطاب',
            'party_a_role' => 'Company',
            'party_b_role' => 'Client',
            'content' => [
                self::block('', ''),
            ],
        ];
    }
}
