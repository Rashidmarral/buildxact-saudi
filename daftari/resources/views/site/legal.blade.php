@extends('layouts.site')

@section('title', ($page === 'terms' ? __('Terms of Service') : __('Privacy Policy')) . ' · Daftari')

@section('content')
<section class="mx-auto max-w-3xl px-6 py-16 prose prose-slate">
    @if ($page === 'terms')
        <h1 class="text-3xl font-extrabold text-slate-900">{{ __('Terms of Service') }}</h1>
        <p class="mt-4 text-slate-500 text-sm">{{ __('Last updated') }}: {{ now()->format('Y-m-d') }}</p>
        <div class="mt-8 space-y-6 text-slate-600">
            <p>{{ __('Welcome to Daftari. These Terms of Service ("Terms") are a legal agreement between you (or the business you represent) and Daftari governing your access to and use of the Daftari accounting and e-invoicing platform (the "Service"). By creating an account or otherwise using the Service, you agree to be bound by these Terms. If you are agreeing on behalf of a company, you confirm that you are authorized to bind that company.') }}</p>

            <h2 class="text-xl font-bold text-slate-900">{{ __('1. The Service') }}</h2>
            <p>{{ __('Daftari provides cloud-based accounting, invoicing, expense, inventory, and ZATCA-compliant e-invoicing tools for businesses operating in Saudi Arabia. The Service is provided on a subscription basis, and features available to you depend on your selected plan.') }}</p>

            <h2 class="text-xl font-bold text-slate-900">{{ __('2. Your account') }}</h2>
            <p>{{ __('You are responsible for maintaining the confidentiality of your account credentials and for all activity that occurs under your account. Notify us immediately if you suspect unauthorized access. You must provide accurate registration information and keep it up to date.') }}</p>

            <h2 class="text-xl font-bold text-slate-900">{{ __('3. Subscriptions and billing') }}</h2>
            <p>{{ __('Plans, pricing, and billing cycles are described on our Pricing page. Subscriptions renew automatically for successive billing periods unless cancelled before the renewal date. Fees are charged in advance and, except where required by law or expressly stated otherwise, are non-refundable. We may change our pricing or plans; we will give you reasonable advance notice before any change takes effect on your account.') }}</p>

            <h2 class="text-xl font-bold text-slate-900">{{ __('4. Your data') }}</h2>
            <p>{{ __('You retain all ownership rights to the invoices, clients, financial records, and other business data you enter into or generate through Daftari ("Your Data"). You grant us a limited license to host, process, and transmit Your Data solely to provide and support the Service. We do not sell Your Data to third parties. On termination of your account, you may export Your Data, and we will retain a copy for a reasonable period thereafter as described in our Privacy Policy before permanent deletion.') }}</p>

            <h2 class="text-xl font-bold text-slate-900">{{ __('5. ZATCA e-invoicing and regulatory compliance') }}</h2>
            <p>{{ __('Daftari helps you generate, format, and transmit e-invoices in line with the Zakat, Tax and Customs Authority\'s (ZATCA) e-invoicing regulations. You remain solely responsible for the accuracy of the tax, VAT, and business information you enter, for your own compliance with Saudi tax law and ZATCA requirements, and for any filings made using data from the Service. Daftari is a software tool, not a substitute for qualified tax, legal, or accounting advice.') }}</p>

            <h2 class="text-xl font-bold text-slate-900">{{ __('6. Acceptable use') }}</h2>
            <p>{{ __('You agree not to: use the Service for any unlawful purpose or to submit fraudulent tax documents; attempt to disrupt, reverse-engineer, or gain unauthorized access to the Service or other customers\' data; upload malicious code; or use the Service in a way that violates the rights of any third party.') }}</p>

            <h2 class="text-xl font-bold text-slate-900">{{ __('7. Third-party services') }}</h2>
            <p>{{ __('The Service may integrate with third-party providers you choose to configure, such as payment gateways, SMS/WhatsApp messaging providers, and email delivery services. Your use of those integrations is also subject to that third party\'s own terms, and Daftari is not responsible for their acts or omissions.') }}</p>

            <h2 class="text-xl font-bold text-slate-900">{{ __('8. Intellectual property') }}</h2>
            <p>{{ __('Daftari and its licensors retain all rights, title, and interest in the Service itself, including its software, design, and branding. Nothing in these Terms transfers any of that intellectual property to you, other than the limited right to use the Service as intended.') }}</p>

            <h2 class="text-xl font-bold text-slate-900">{{ __('9. Termination') }}</h2>
            <p>{{ __('You may cancel your subscription at any time from your account settings. We may suspend or terminate your access if you materially breach these Terms, fail to pay applicable fees, or if required to do so by law. On termination, your right to use the Service ends, though certain provisions of these Terms (such as data handling, liability, and governing law) survive.') }}</p>

            <h2 class="text-xl font-bold text-slate-900">{{ __('10. Disclaimer and limitation of liability') }}</h2>
            <p>{{ __('The Service is provided "as is" and "as available." To the fullest extent permitted by law, Daftari disclaims all warranties, express or implied, and shall not be liable for indirect, incidental, or consequential damages arising from your use of the Service. Our aggregate liability for any claim relating to the Service is limited to the fees you paid us in the twelve months preceding the claim.') }}</p>

            <h2 class="text-xl font-bold text-slate-900">{{ __('11. Governing law') }}</h2>
            <p>{{ __('These Terms are governed by the laws of the Kingdom of Saudi Arabia. Any dispute arising from these Terms or your use of the Service shall be subject to the exclusive jurisdiction of the competent courts of Saudi Arabia.') }}</p>

            <h2 class="text-xl font-bold text-slate-900">{{ __('12. Changes to these Terms') }}</h2>
            <p>{{ __('We may update these Terms from time to time. We will post the updated Terms on this page with a revised "Last updated" date, and for material changes we will provide additional notice (such as email or an in-app notice). Continued use of the Service after a change takes effect constitutes acceptance of the revised Terms.') }}</p>

            <h2 class="text-xl font-bold text-slate-900">{{ __('13. Contact us') }}</h2>
            <p>{{ __('Questions about these Terms can be sent through our Contact page.') }}</p>
        </div>
    @else
        <h1 class="text-3xl font-extrabold text-slate-900">{{ __('Privacy Policy') }}</h1>
        <p class="mt-4 text-slate-500 text-sm">{{ __('Last updated') }}: {{ now()->format('Y-m-d') }}</p>
        <div class="mt-8 space-y-6 text-slate-600">
            <p>{{ __('This Privacy Policy explains how Daftari collects, uses, discloses, and protects personal data when you use our accounting and e-invoicing platform, in line with the Kingdom of Saudi Arabia\'s Personal Data Protection Law (PDPL) and its implementing regulations.') }}</p>

            <h2 class="text-xl font-bold text-slate-900">{{ __('Information we collect') }}</h2>
            <p>{{ __('Account details (name, email, phone number); company information (business name, VAT registration number, Commercial Registration number, address); the invoicing, expense, client, supplier, and other financial data you enter into the Service; billing and payment details processed by our payment gateway providers; and technical/usage data such as IP address, browser type, device information, and log data collected automatically when you use the Service.') }}</p>

            <h2 class="text-xl font-bold text-slate-900">{{ __('How we use your information') }}</h2>
            <p>{{ __('We use personal data to: provide, operate, and maintain the Service; process your subscription billing; generate and transmit e-invoices to ZATCA on your behalf where you use that feature; send you service-related communications (invoices, receipts, security alerts, support responses); detect and prevent fraud, abuse, and security incidents; and comply with our legal obligations, including tax and accounting record-keeping requirements under Saudi law.') }}</p>

            <h2 class="text-xl font-bold text-slate-900">{{ __('How we share information') }}</h2>
            <p>{{ __('We do not sell personal data. We share it only with: service providers who process data on our behalf under contract (such as cloud hosting, email delivery, payment gateways, and error-monitoring providers), each limited to what they need to perform their function; the Zakat, Tax and Customs Authority (ZATCA), where you use our e-invoicing features, as required for e-invoice clearance and reporting; and other parties where required by law, legal process, or to protect the rights, property, or safety of Daftari, our customers, or others.') }}</p>

            <h2 class="text-xl font-bold text-slate-900">{{ __('Data retention') }}</h2>
            <p>{{ __('We retain personal data and business records for as long as your account is active and thereafter for the period needed to fulfil the purposes described in this policy, including statutory bookkeeping and tax record-retention periods required under Saudi law, after which it is deleted or anonymized.') }}</p>

            <h2 class="text-xl font-bold text-slate-900">{{ __('Data security') }}</h2>
            <p>{{ __('We apply technical and organizational measures designed to protect personal data against unauthorized access, loss, or misuse, including encryption of data in transit, access controls, and regular security review. No method of transmission or storage is completely secure, and we cannot guarantee absolute security.') }}</p>

            <h2 class="text-xl font-bold text-slate-900">{{ __('Cross-border data transfer') }}</h2>
            <p>{{ __('Where personal data is transferred outside the Kingdom of Saudi Arabia (for example, to a cloud infrastructure or service provider located abroad), we take steps to ensure that transfer complies with the PDPL\'s requirements, including using providers that offer an adequate level of protection or are subject to appropriate contractual safeguards.') }}</p>

            <h2 class="text-xl font-bold text-slate-900">{{ __('Your rights') }}</h2>
            <p>{{ __('Subject to the PDPL, you have the right to know what personal data we hold about you, request access to or a copy of it, request correction of inaccurate data, request deletion of your data (subject to our legal retention obligations), and withdraw consent where processing is based on consent. To exercise any of these rights, contact us through our Contact page.') }}</p>

            <h2 class="text-xl font-bold text-slate-900">{{ __('Cookies') }}</h2>
            <p>{{ __('We use essential cookies and similar technologies required for the Service to function (such as keeping you signed in and remembering your language preference). We do not use these to track you across other websites.') }}</p>

            <h2 class="text-xl font-bold text-slate-900">{{ __('Changes to this policy') }}</h2>
            <p>{{ __('We may update this Privacy Policy from time to time. We will post the updated policy on this page with a revised "Last updated" date, and for material changes we will provide additional notice.') }}</p>

            <h2 class="text-xl font-bold text-slate-900">{{ __('Contact us') }}</h2>
            <p>{{ __('Questions about this Privacy Policy, or requests relating to your personal data, can be sent through our Contact page.') }}</p>
        </div>
    @endif
</section>
@endsection
