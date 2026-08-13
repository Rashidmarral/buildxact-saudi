@extends('layouts.site')

@section('title', __('Features') . ' · Daftari')

@section('content')
<section class="mx-auto max-w-4xl px-6 py-16">
    <h1 class="text-4xl font-extrabold text-slate-900">{{ __('Features — everything for VAT invoicing, in one place') }}</h1>
    <p class="mt-4 text-lg text-slate-600">{{ __('Daftari brings invoicing, expenses, and VAT reporting together for Saudi businesses. Some capabilities below are live today; others are on our near-term roadmap and clearly marked.') }}</p>
</section>

<section class="mx-auto max-w-6xl px-6 pb-16">
    <div class="grid md:grid-cols-3 gap-6">
        @foreach ([
            ['n' => 1, 'i' => '🧾', 't' => __('Create and send invoices'), 'd' => __('Build VAT-compliant invoices with line items and send them to clients, tracked by status.'), 'live' => true],
            ['n' => 2, 'i' => '🏷️', 't' => __('Apply tax rates'), 'd' => __('Set a default VAT rate per item and override it per invoice line when needed.'), 'live' => true],
            ['n' => 3, 'i' => '📱', 't' => __('ZATCA-style QR codes'), 'd' => __('Every tax invoice includes a scannable QR code encoding seller, VAT number, timestamp, and totals.'), 'live' => true],
            ['n' => 4, 'i' => '💳', 't' => __('Expense tracking'), 'd' => __('Record and categorize purchases and their recoverable VAT so nothing falls through the cracks.'), 'live' => true],
            ['n' => 5, 'i' => '📈', 't' => __('VAT return report'), 'd' => __('A summary of output VAT, input VAT, and net VAT due for any period, ready to review before filing.'), 'live' => true],
            ['n' => 6, 'i' => '🧑‍🤝‍🧑', 't' => __('Users & roles'), 'd' => __('Invite your team with owner or staff roles, while billing and company settings stay owner-only.'), 'live' => true],
            ['n' => 7, 'i' => '🏦', 't' => __('Account reconciliation'), 'd' => __('Match bank and cash activity against your records so your numbers reflect reality.'), 'live' => false],
            ['n' => 8, 'i' => '💰', 't' => __('Cash, bank accounts & cards'), 'd' => __('Track multiple balances and accounts in one place.'), 'live' => false],
            ['n' => 9, 'i' => '📄', 't' => __('Supplier bills'), 'd' => __('Organize purchases and costs from your suppliers for accurate reporting.'), 'live' => false],
            ['n' => 10, 'i' => '📝', 't' => __('Purchase orders'), 'd' => __('Document purchase requests before they become costs, for clearer purchasing visibility.'), 'live' => false],
            ['n' => 11, 'i' => '↩️', 't' => __('Debit notes'), 'd' => __('Record purchase adjustments and price or quantity variances in an organized way.'), 'live' => false],
            ['n' => 12, 'i' => '💬', 't' => __('Quotes'), 'd' => __('Send a professional quote and convert it straight into an invoice once approved.'), 'live' => false],
            ['n' => 13, 'i' => '📊', 't' => __('Cash flow report'), 'd' => __('Understand cash in and cash out for clearer visibility into liquidity.'), 'live' => false],
            ['n' => 14, 'i' => '🗂️', 't' => __('Cost centers'), 'd' => __('Allocate costs to departments, branches, or activities for clearer breakdowns.'), 'live' => false],
            ['n' => 15, 'i' => '🌍', 't' => __('Multi-currency'), 'd' => __('Work in more than one currency and track exchange rates clearly.'), 'live' => false],
            ['n' => 16, 'i' => '🏬', 't' => __('Branches'), 'd' => __('Organize invoices, bills, and expenses by branch and track performance separately.'), 'live' => false],
            ['n' => 17, 'i' => '🎨', 't' => __('Invoice & quote templates'), 'd' => __('Multiple document templates that unify the look of your paperwork.'), 'live' => false],
        ] as $feature)
            <div class="relative rounded-xl border border-slate-100 bg-white p-6">
                @unless ($feature['live'])
                    <span class="absolute top-4 end-4 rounded-full bg-amber-50 text-amber-700 text-xs font-semibold px-2 py-0.5">{{ __('Coming soon') }}</span>
                @endunless
                <div class="text-2xl">{{ $feature['i'] }}</div>
                <h3 class="mt-3 font-semibold text-slate-900">{{ $feature['t'] }}</h3>
                <p class="mt-2 text-sm text-slate-500">{{ $feature['d'] }}</p>
            </div>
        @endforeach
    </div>
</section>

<section class="mx-auto max-w-7xl px-6 py-20">
    <div class="rounded-2xl bg-brand-600 px-8 py-14 text-center text-white">
        <h2 class="text-2xl md:text-3xl font-bold">{{ __('Ready to simplify your VAT invoicing?') }}</h2>
        <p class="mt-3 text-brand-50">{{ __('Join Saudi businesses managing their invoicing and VAT with Daftari.') }}</p>
        <a href="{{ route('register') }}" class="mt-6 inline-block rounded-lg bg-white px-6 py-3 font-semibold text-brand-700 hover:bg-brand-50">{{ __('Start your free trial') }}</a>
    </div>
</section>
@endsection
