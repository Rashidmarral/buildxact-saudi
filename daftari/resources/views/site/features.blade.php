@extends('layouts.site')

@section('title', __('Features') . ' · Daftari')

@section('content')
<section class="mx-auto max-w-5xl px-6 py-16 text-center">
    <h1 class="text-4xl font-extrabold text-slate-900">{{ __('Built for how Saudi businesses actually invoice') }}</h1>
    <p class="mt-4 text-lg text-slate-600">{{ __('From your first quote to your VAT return, Daftari keeps everything in one place.') }}</p>
</section>

<section class="mx-auto max-w-6xl px-6 pb-24 space-y-16">
    @foreach ([
        [
            'title' => __('VAT-compliant invoicing'),
            'desc' => __('Create standard and simplified tax invoices with automatic 15% VAT calculation per line item, sequential numbering per company, and a ZATCA-style QR code embedding seller name, VAT number, timestamp, invoice total, and VAT total.'),
            'points' => [__('Standard & simplified invoice types'), __('Per-line VAT rates'), __('Scannable QR code on every invoice'), __('Sequential, gap-free numbering')],
        ],
        [
            'title' => __('Payments & aging'),
            'desc' => __('Record partial or full payments against any invoice and track outstanding balances automatically — know exactly who owes you and how much.'),
            'points' => [__('Partial payment support'), __('Automatic status (draft, sent, paid, overdue)'), __('Outstanding balance at a glance')],
        ],
        [
            'title' => __('Expense tracking'),
            'desc' => __('Log vendor purchases with recoverable input VAT, organized by category, so your VAT report nets output VAT against input VAT automatically.'),
            'points' => [__('Custom expense categories'), __('Input VAT capture'), __('Monthly and yearly views')],
        ],
        [
            'title' => __('Clients, items & catalog'),
            'desc' => __('Keep a reusable directory of clients (with their VAT/CR numbers) and priced items or services so building an invoice takes seconds.'),
            'points' => [__('Client VAT & CR numbers'), __('Per-item default VAT rate'), __('Bilingual names (EN/AR)')],
        ],
        [
            'title' => __('Team & roles'),
            'desc' => __('Invite staff or your bookkeeper to help manage invoices and expenses, while billing and company settings stay owner-only.'),
            'points' => [__('Owner and staff roles'), __('Per-company data isolation'), __('Add or remove teammates anytime')],
        ],
        [
            'title' => __('Subscription billing'),
            'desc' => __('Pick a plan that fits your business, pay monthly or yearly in SAR, and manage your subscription from your dashboard.'),
            'points' => [__('Free trial, no card required'), __('Monthly or yearly billing'), __('Upgrade or downgrade anytime')],
        ],
    ] as $i => $section)
        <div class="grid md:grid-cols-2 gap-10 items-center {{ $i % 2 === 1 ? 'md:[direction:rtl]' : '' }}">
            <div class="{{ $i % 2 === 1 ? 'md:[direction:ltr]' : '' }}">
                <h2 class="text-2xl font-bold text-slate-900">{{ $section['title'] }}</h2>
                <p class="mt-3 text-slate-600">{{ $section['desc'] }}</p>
                <ul class="mt-5 space-y-2">
                    @foreach ($section['points'] as $point)
                        <li class="flex items-center gap-2 text-sm text-slate-700"><span class="text-brand-600">✓</span>{{ $point }}</li>
                    @endforeach
                </ul>
            </div>
            <div class="{{ $i % 2 === 1 ? 'md:[direction:ltr]' : '' }} rounded-2xl bg-slate-50 border border-slate-100 h-56 flex items-center justify-center text-6xl">
                {{ ['🧾','💵','💳','👥','🧑‍🤝‍🧑','💰'][$i] }}
            </div>
        </div>
    @endforeach
</section>
@endsection
