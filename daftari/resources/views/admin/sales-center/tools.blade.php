@extends('layouts.admin')

@section('title', __('Sales Center'))

@section('content')
@php
    $whatsappAr = "مرحباً {الاسم} 👋\nمعك {اسمك} من دفتري. لاحظت أن نشاطكم التجاري قد يحتاج فوترة إلكترونية متوافقة مع متطلبات هيئة الزكاة والضريبة والجمارك (فاتورة). نساعد أصحاب الأعمال على إصدار فواتير ضريبية متوافقة، مع محاسبة كاملة ومخزون ورواتب، خلال أقل من 10 دقائق إعداد.\nهل تحب أن أرسل لك عرضاً سريعاً (دقيقتين) يوضح كيف يعمل؟";
    $whatsappEn = "Hi {name} 👋\nThis is {your name} from Daftari. Noticed your business may need ZATCA-compliant e-invoicing (real tax invoices with a QR code). We help Saudi businesses issue compliant invoices — plus full accounting, inventory and payroll — in under 10 minutes of setup.\nMind if I send a quick 2-minute walkthrough of how it works?";
    $emailSubjectAr = 'فوترة إلكترونية متوافقة مع هيئة الزكاة والضريبة والجمارك لمنشأتكم — دفتري';
    $emailBodyAr = "مرحباً {الاسم}،\n\nملاحظة سريعة — تطبيق الفوترة الإلكترونية من هيئة الزكاة والضريبة والجمارك يعني أن معظم المنشآت في المملكة تحتاج الآن فواتير ضريبية متوافقة تحمل رمز QR (ولاحقاً ربط وإبلاغ لحظي).\n\nدفتري منصة محاسبة وفوترة إلكترونية مبنية خصيصاً للمنشآت السعودية: فواتير وضريبة القيمة المضافة ومصروفات ومخزون ورواتب في مكان واحد، مع توافق هيئة الزكاة والضريبة والجمارك من اليوم الأول.\n\nهل تسمح لي بعرض سريع (10 دقائق) هذا الأسبوع؟ يسعدني الالتزام بوقتكم.\n\nمع التحية،\n{اسمك}\n{رقم الجوال/واتساب}";
    $emailSubjectEn = 'ZATCA-compliant e-invoicing for {company} — quick question';
    $emailBodyEn = "Hi {name},\n\nQuick note — ZATCA's e-invoicing rollout means most businesses in Saudi Arabia now need compliant tax invoices with a QR code (and eventually real-time reporting).\n\nDaftari is an accounting + e-invoicing platform built specifically for Saudi businesses: invoices, VAT, expenses, inventory and payroll in one place, with ZATCA compliance built in from day one.\n\nWould you be open to a 10-minute walkthrough this week? Happy to work around your schedule.\n\nBest,\n{your name}\n{phone/WhatsApp}";
    $emailFullAr = $emailSubjectAr."\n\n".$emailBodyAr;
    $emailFullEn = $emailSubjectEn."\n\n".$emailBodyEn;
    $objections = [
        ['q' => __('"We already use Excel / a notebook."'), 'a' => __('Excel doesn\'t generate a ZATCA-compliant QR code or e-invoice — that\'s a real compliance gap once their wave is enforced. Daftari keeps it just as simple, but compliant.')],
        ['q' => __('"We already have an accountant."'), 'a' => __('Great — Daftari works alongside their accountant. If that accountant joins our Partner Program, they can manage the account and earn commission on it too.')],
        ['q' => __('"It\'s too expensive."'), 'a' => __('Compare it to the cost of one ZATCA fine for non-compliant invoices, or the hours their team spends doing this manually. Walk them to the lowest plan on the Pricing page.')],
        ['q' => __('"We\'re not ready for ZATCA yet."'), 'a' => __('Confirm which wave/phase their VAT registration actually falls under — many businesses have less time than they think. Point them to the Compliance page.')],
        ['q' => __('"Let me think about it."'), 'a' => __('Fair enough — send the one-pager below right now and set a specific follow-up: "I\'ll check back Thursday, does that work?"')],
        ['q' => __('"We tried another system and it was complicated."'), 'a' => __('Daftari is built specifically for Saudi SMEs, not adapted from a global tool. Offer a free walkthrough using their own real numbers.')],
    ];
@endphp

<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-900">{{ __('Sales Center') }}</h1>
    <p class="text-sm text-slate-500 mt-1">{{ __('Ready-to-use outreach material — copy, personalize the placeholders in {braces}, and send.') }}</p>
</div>

@include('admin.sales-center.partials.tabs')

<div class="space-y-6 max-w-3xl">

    {{-- One-pager pitch --}}
    <div class="bg-white rounded-xl border border-slate-100 p-6">
        <h2 class="font-semibold text-slate-900">{{ __('One-pager pitch') }}</h2>
        <p class="mt-1 text-sm text-slate-500">{{ __('Read this out loud on a call, or paste it into a one-page PDF/slide to leave behind.') }}</p>
        <div class="mt-4 rounded-lg bg-slate-50 border border-slate-100 p-4 text-sm text-slate-700 space-y-2">
            <p class="font-semibold text-slate-900">{{ __('Daftari — ZATCA-compliant accounting, set up in under 10 minutes') }}</p>
            <ul class="list-disc ms-5 space-y-1">
                <li>{{ __('Real tax invoices with a ZATCA QR code from day one — no separate compliance project.') }}</li>
                <li>{{ __('Full accounting: invoices, expenses, inventory, payroll, bank reconciliation — one place, no spreadsheets.') }}</li>
                <li>{{ __('Arabic and English, built for how Saudi businesses actually invoice.') }}</li>
                <li>{{ __('Live in one sitting — most businesses are issuing their first compliant invoice the same day.') }}</li>
            </ul>
            <p>{{ __('Ask: "What are you using for invoicing today, and has anyone confirmed your ZATCA wave with you?"') }}</p>
        </div>
    </div>

    {{-- WhatsApp script --}}
    <div class="bg-white rounded-xl border border-slate-100 p-6">
        <h2 class="font-semibold text-slate-900">{{ __('WhatsApp opening message') }}</h2>
        <p class="mt-1 text-sm text-slate-500">{{ __('First message to a new contact. Keep it short — the goal is a reply, not a full pitch.') }}</p>

        <div class="mt-4 space-y-4">
            <div>
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase text-slate-400">{{ __('Arabic') }}</span>
                    <div class="flex items-center gap-3">
                        <a href="https://wa.me/?text={{ urlencode($whatsappAr) }}" target="_blank" rel="noopener" class="text-xs font-semibold text-emerald-600 hover:underline">{{ __('Open in WhatsApp') }}</a>
                        <button type="button" x-data="{ copied: false }" @click="navigator.clipboard.writeText(@js($whatsappAr)); copied = true; setTimeout(() => copied = false, 2000)" class="text-xs font-semibold text-brand-700 hover:underline">
                            <span x-show="!copied">{{ __('Copy') }}</span>
                            <span x-show="copied" x-cloak>{{ __('Copied!') }}</span>
                        </button>
                    </div>
                </div>
                <pre dir="rtl" class="mt-1 whitespace-pre-wrap rounded-lg bg-slate-50 border border-slate-100 p-3 text-sm text-slate-700 font-sans">{{ $whatsappAr }}</pre>
            </div>
            <div>
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase text-slate-400">{{ __('English') }}</span>
                    <div class="flex items-center gap-3">
                        <a href="https://wa.me/?text={{ urlencode($whatsappEn) }}" target="_blank" rel="noopener" class="text-xs font-semibold text-emerald-600 hover:underline">{{ __('Open in WhatsApp') }}</a>
                        <button type="button" x-data="{ copied: false }" @click="navigator.clipboard.writeText(@js($whatsappEn)); copied = true; setTimeout(() => copied = false, 2000)" class="text-xs font-semibold text-brand-700 hover:underline">
                            <span x-show="!copied">{{ __('Copy') }}</span>
                            <span x-show="copied" x-cloak>{{ __('Copied!') }}</span>
                        </button>
                    </div>
                </div>
                <pre class="mt-1 whitespace-pre-wrap rounded-lg bg-slate-50 border border-slate-100 p-3 text-sm text-slate-700 font-sans">{{ $whatsappEn }}</pre>
            </div>
        </div>
    </div>

    {{-- Cold email script --}}
    <div class="bg-white rounded-xl border border-slate-100 p-6">
        <h2 class="font-semibold text-slate-900">{{ __('Cold email template') }}</h2>
        <p class="mt-1 text-sm text-slate-500">{{ __('Use when you have an email address but no warm intro yet.') }}</p>

        <div class="mt-4 space-y-4">
            <div>
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase text-slate-400">{{ __('Arabic') }}</span>
                    <button type="button" x-data="{ copied: false }" @click="navigator.clipboard.writeText(@js($emailFullAr)); copied = true; setTimeout(() => copied = false, 2000)" class="text-xs font-semibold text-brand-700 hover:underline">
                        <span x-show="!copied">{{ __('Copy') }}</span>
                        <span x-show="copied" x-cloak>{{ __('Copied!') }}</span>
                    </button>
                </div>
                <p dir="rtl" class="mt-1 text-sm font-semibold text-slate-800">{{ $emailSubjectAr }}</p>
                <pre dir="rtl" class="mt-1 whitespace-pre-wrap rounded-lg bg-slate-50 border border-slate-100 p-3 text-sm text-slate-700 font-sans">{{ $emailBodyAr }}</pre>
            </div>
            <div>
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase text-slate-400">{{ __('English') }}</span>
                    <button type="button" x-data="{ copied: false }" @click="navigator.clipboard.writeText(@js($emailFullEn)); copied = true; setTimeout(() => copied = false, 2000)" class="text-xs font-semibold text-brand-700 hover:underline">
                        <span x-show="!copied">{{ __('Copy') }}</span>
                        <span x-show="copied" x-cloak>{{ __('Copied!') }}</span>
                    </button>
                </div>
                <p class="mt-1 text-sm font-semibold text-slate-800">{{ $emailSubjectEn }}</p>
                <pre class="mt-1 whitespace-pre-wrap rounded-lg bg-slate-50 border border-slate-100 p-3 text-sm text-slate-700 font-sans">{{ $emailBodyEn }}</pre>
            </div>
        </div>
    </div>

    {{-- Objection handling --}}
    <div class="bg-white rounded-xl border border-slate-100 p-6">
        <h2 class="font-semibold text-slate-900">{{ __('Objection-handling cheat sheet') }}</h2>
        <p class="mt-1 text-sm text-slate-500">{{ __('The six objections that come up most — and a direct way to answer each one.') }}</p>
        <div class="mt-4 divide-y divide-slate-50 border border-slate-100 rounded-lg overflow-hidden">
            @foreach ($objections as $o)
                <div class="px-4 py-3">
                    <p class="text-sm font-semibold text-slate-800">{{ $o['q'] }}</p>
                    <p class="mt-1 text-sm text-slate-600">{{ $o['a'] }}</p>
                </div>
            @endforeach
        </div>
    </div>

</div>
@endsection
