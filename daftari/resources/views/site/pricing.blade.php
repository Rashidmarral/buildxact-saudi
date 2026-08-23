@extends('layouts.site')

@section('title', __('Pricing') . ' · Daftari')

@section('content')
<section class="mx-auto max-w-4xl px-6 py-16 text-center">
    <h1 class="text-4xl font-extrabold text-slate-900">{{ __('Simple, transparent pricing') }}</h1>
    <p class="mt-4 text-lg text-slate-600">{{ __('All prices in SAR. Cancel anytime. Every plan starts with a :days-day free trial.', ['days' => config('daftari.trial_days')]) }}</p>

    <div class="mt-8 inline-flex items-center gap-1 rounded-full border border-slate-200 bg-white p-1" id="cycle-toggle">
        <button type="button" data-cycle="monthly" class="cycle-btn rounded-full px-4 py-2 text-sm font-semibold" aria-pressed="true">{{ __('Monthly') }}</button>
        <button type="button" data-cycle="yearly" class="cycle-btn rounded-full px-4 py-2 text-sm font-semibold" aria-pressed="false">{{ __('Annual') }} <span class="text-brand-600">({{ __('save up to 17%') }})</span></button>
    </div>
</section>

<section class="mx-auto max-w-6xl px-6 pb-16 grid md:grid-cols-3 gap-8">
    @foreach ($plans as $plan)
        @php
            $yearlyMonthlyEquivalent = $plan->price_yearly > 0 ? $plan->price_yearly / 12 : 0;
            $yearlyMonthlyOriginal = $plan->price_yearly_original > 0 ? $plan->price_yearly_original / 12 : 0;
        @endphp
        <div class="rounded-2xl border {{ $loop->index === 1 ? 'border-brand-500 shadow-lg ring-1 ring-brand-500' : 'border border-slate-200' }} bg-white p-8 flex flex-col relative">
            @if ($loop->index === 1)
                <span class="self-start mb-3 rounded-full bg-brand-600 text-white text-xs font-semibold px-3 py-1">{{ __('Best value') }}</span>
            @endif
            <h3 class="text-xl font-bold text-slate-900">{{ app()->getLocale() === 'ar' && $plan->name_ar ? $plan->name_ar : $plan->name }}</h3>

            <div class="mt-4 price-display" data-monthly="{{ number_format($plan->price_monthly, 0) }}" data-monthly-original="{{ number_format($plan->price_monthly_original, 0) }}" data-yearly-equivalent="{{ number_format($yearlyMonthlyEquivalent, 0) }}" data-yearly-equivalent-original="{{ number_format($yearlyMonthlyOriginal, 0) }}">
                @if ($plan->price_monthly_original > $plan->price_monthly)
                    <span class="text-slate-400 line-through text-lg price-original">SAR {{ number_format($plan->price_monthly_original, 0) }}</span>
                @endif
                <span class="text-3xl font-extrabold text-slate-900 price-amount">SAR {{ number_format($plan->price_monthly, 0) }}</span>
                <span class="text-slate-500 text-sm">/{{ __('month') }}</span>
            </div>
            <p class="text-xs text-slate-400 mt-1 price-sub">{{ __('or SAR :price/year, billed annually', ['price' => number_format($plan->price_yearly, 0)]) }}</p>

            <ul class="mt-6 space-y-3 text-sm text-slate-600 flex-1">
                <li class="flex items-center gap-2"><span class="text-brand-600">✓</span>{{ $plan->max_users ? __(':count team members', ['count' => $plan->max_users]) : __('Unlimited team members') }}</li>
                <li class="flex items-center gap-2"><span class="text-brand-600">✓</span>{{ $plan->max_invoices_per_month ? __(':count invoices/month', ['count' => $plan->max_invoices_per_month]) : __('Unlimited invoices') }}</li>
                @foreach (($plan->features ?? []) as $feature)
                    <li class="flex items-center gap-2"><span class="text-brand-600">✓</span>{{ $feature }}</li>
                @endforeach
            </ul>
            <a href="{{ route('register') }}" class="mt-8 block text-center rounded-lg {{ $loop->index === 1 ? 'bg-brand-600 text-white hover:bg-brand-700' : 'border border-slate-200 text-slate-700 hover:border-brand-300' }} px-6 py-3 font-semibold">
                {{ __('Start free trial') }}
            </a>
        </div>
    @endforeach
</section>

<script>
(function () {
    const toggle = document.getElementById('cycle-toggle');
    if (!toggle) return;
    const buttons = toggle.querySelectorAll('.cycle-btn');
    const displays = document.querySelectorAll('.price-display');
    const subs = document.querySelectorAll('.price-sub');

    function setActive(cycle) {
        buttons.forEach(btn => {
            const active = btn.dataset.cycle === cycle;
            btn.setAttribute('aria-pressed', active ? 'true' : 'false');
            btn.classList.toggle('bg-brand-600', active);
            btn.classList.toggle('text-white', active);
            btn.classList.toggle('text-slate-600', !active);
        });
        displays.forEach(el => {
            const amount = cycle === 'yearly' ? el.dataset.yearlyEquivalent : el.dataset.monthly;
            const original = cycle === 'yearly' ? el.dataset.yearlyEquivalentOriginal : el.dataset.monthlyOriginal;
            el.querySelector('.price-amount').textContent = 'SAR ' + amount;
            const originalEl = el.querySelector('.price-original');
            if (originalEl) originalEl.textContent = 'SAR ' + original;
        });
        subs.forEach(el => { el.style.display = cycle === 'yearly' ? 'none' : ''; });
    }

    buttons.forEach(btn => btn.addEventListener('click', () => setActive(btn.dataset.cycle)));
    setActive('monthly');
})();
</script>

@php
    $check = '<span class="text-brand-600">✓</span>';
    $cross = '<span class="text-slate-300">—</span>';
    $limitCell = fn ($limit) => $limit ? $limit : __('Unlimited');
    $boolCell = fn ($has) => $has ? $check : $cross;

    $sections = [
        [__('Invoicing & VAT'), [
            [__('Invoices per month'), fn ($p) => $limitCell($p->max_invoices_per_month)],
            [__('VAT-compliant invoicing'), fn () => $check],
            [__('ZATCA Phase 1 QR code on every invoice'), fn () => $check],
            [__('ZATCA Phase 2 integration (real-time clearance & reporting)'), fn ($p) => $boolCell($p->has_zatca_phase2)],
            [__('Credit notes'), fn () => $check],
            [__('Invoice templates'), fn ($p) => $limitCell($p->max_invoice_templates)],
            [__('Recurring invoices'), fn ($p) => $boolCell($p->has_recurring_invoices)],
            [__('Quotations & proforma invoices'), fn ($p) => $boolCell($p->has_quotations)],
            [__('Company stamp on documents'), fn ($p) => $boolCell($p->has_stamps)],
        ]],
        [__('Customers & sales'), [
            [__('Customers'), fn ($p) => $limitCell($p->max_customers)],
            [__('Salespersons'), fn () => $check],
            [__('Projects'), fn () => $check],
        ]],
        [__('Purchases & inventory'), [
            [__('Suppliers'), fn ($p) => $limitCell($p->max_suppliers)],
            [__('Bills & expenses'), fn () => $check],
            [__('Purchase orders'), fn ($p) => $boolCell($p->has_purchase_orders)],
            [__('Debit notes (purchase returns)'), fn ($p) => $boolCell($p->has_debit_notes)],
            [__('Customs declarations'), fn () => $check],
            [__('Items, units & warehouses'), fn ($p) => $limitCell($p->max_warehouses)],
            [__('Stock adjustments & valuation reports'), fn () => $check],
        ]],
        [__('Accounting & reports'), [
            [__('Chart of accounts & journals'), fn () => $check],
            [__('Sales, expense & cash flow reports'), fn () => $check],
            [__('Trial balance & account statements'), fn () => $check],
            [__('Financial statements (balance sheet & income statement)'), fn ($p) => $boolCell($p->has_financial_statements)],
            [__('VAT return report'), fn ($p) => $boolCell($p->has_vat_return_report)],
            [__('Cost centers'), fn ($p) => $boolCell($p->has_cost_centers)],
        ]],
        [__('Team & customization'), [
            [__('Team members'), fn ($p) => $limitCell($p->max_users)],
            [__('Branches'), fn ($p) => $limitCell($p->max_branches)],
            [__('Bank & cash accounts'), fn ($p) => $limitCell($p->max_bank_accounts)],
            [__('Custom roles & permissions'), fn ($p) => $boolCell($p->has_roles_permissions)],
            [__('Company audit log'), fn () => $check],
        ]],
    ];
@endphp

<section class="mx-auto max-w-6xl px-6 pb-16">
    <h2 class="text-2xl font-bold text-slate-900 text-center mb-8">{{ __("What's included in each plan?") }}</h2>
    <div class="overflow-x-auto rounded-xl border border-slate-100">
        <table class="w-full text-sm bg-white">
            <thead>
                <tr class="bg-slate-900 text-white text-left">
                    <th class="px-4 py-3 font-medium">{{ __('Feature') }}</th>
                    @foreach ($plans as $plan)
                        <th class="px-4 py-3 font-medium text-center">{{ app()->getLocale() === 'ar' && $plan->name_ar ? $plan->name_ar : $plan->name }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($sections as [$sectionLabel, $rows])
                    <tr class="bg-slate-50"><td colspan="{{ $plans->count() + 1 }}" class="px-4 py-2 font-semibold text-slate-500 text-xs uppercase">{{ $sectionLabel }}</td></tr>
                    @foreach ($rows as [$rowLabel, $cellFor])
                        <tr class="border-b border-slate-50">
                            <td class="px-4 py-3">{{ $rowLabel }}</td>
                            @foreach ($plans as $plan)
                                <td class="px-4 py-3 text-center">{!! $cellFor($plan) !!}</td>
                            @endforeach
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
    </div>
    <p class="mt-4 text-xs text-slate-400 text-center">
        {{ __('Every feature listed here is fully built and available on the plan shown — nothing on this page is a roadmap item.') }}
        <a href="{{ route('features') }}" class="text-brand-700 hover:underline">{{ __('Explore all features') }} →</a>
    </p>
</section>

<section class="mx-auto max-w-4xl px-6 pb-24">
    <h2 class="text-2xl font-bold text-slate-900 text-center">{{ __('Frequently asked questions') }}</h2>
    <div class="mt-8 space-y-6">
        @foreach ([
            [__('Do I need a credit card to start the trial?'), __('No — start your :days-day trial with just an email address. Add billing details only when you choose a plan.', ['days' => config('daftari.trial_days')])],
            [__('Can I change plans later?'), __('Yes, upgrade or downgrade anytime from your Billing page. Changes apply to your next billing cycle.')],
            [__('Is VAT handled automatically?'), __('Daftari calculates 15% VAT per line item by default and lets you override the rate per item where needed, and totals it into a VAT report.')],
            [__('What payment methods are supported?'), __('We support major Saudi payment gateways for subscription billing; contact us if you need a specific method.')],
        ] as [$q, $a])
            <div class="border-b border-slate-100 pb-6">
                <h3 class="font-semibold text-slate-900">{{ $q }}</h3>
                <p class="mt-2 text-sm text-slate-600">{{ $a }}</p>
            </div>
        @endforeach
    </div>
</section>
@endsection
