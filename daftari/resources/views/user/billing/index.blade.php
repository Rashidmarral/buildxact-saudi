@extends('layouts.app')

@section('title', __('Billing'))

@section('content')
<div class="mb-2">
    <h2 class="text-lg font-semibold text-slate-900">{{ __('Billing') }}</h2>
    <p class="text-sm text-slate-500">{{ __('Manage subscription') }}</p>
</div>

@if ($subscription && $subscription->isTrial())
    <div class="mt-4 mb-6 rounded-xl bg-amber-50 border border-amber-200 px-5 py-4 flex items-center justify-between gap-4">
        <div>
            <p class="font-semibold text-amber-800">{{ __("You're on a free trial") }}</p>
            <p class="text-sm text-amber-700 mt-0.5">{{ __('All features in your plan are available during your trial. Subscribe before it ends to keep uninterrupted access.') }}</p>
        </div>
        <a href="{{ route('app.billing.index', ['tab' => 'plans']) }}" class="shrink-0 rounded-lg bg-amber-500 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-600">{{ __('Subscribe now') }}</a>
    </div>
@endif

@if ($subscription?->cancelled_at)
    <div class="mt-4 mb-6 rounded-xl bg-red-50 border border-red-200 px-5 py-4 flex items-center justify-between gap-4">
        <div>
            <p class="font-semibold text-red-800">{{ __('Subscription cancelled') }}</p>
            <p class="text-sm text-red-700 mt-0.5">{{ __('Your subscription will end on :date. You can keep using :plan until then.', ['date' => $subscription->current_period_end->format('Y-m-d'), 'plan' => $subscription->plan->name]) }}</p>
        </div>
        <form method="POST" action="{{ route('app.billing.resume') }}">
            @csrf
            <button type="submit" class="shrink-0 rounded-lg border border-red-300 bg-white px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-100">{{ __('Keep my subscription') }}</button>
        </form>
    </div>
@endif

<div class="flex items-center gap-2 mb-6 text-sm">
    <a href="{{ route('app.billing.index', ['tab' => 'overview']) }}" class="rounded-lg px-3 py-1.5 {{ $tab === 'overview' ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100' }}">{{ __('Overview') }}</a>
    <a href="{{ route('app.billing.index', ['tab' => 'plans']) }}" class="rounded-lg px-3 py-1.5 {{ $tab === 'plans' ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100' }}">{{ __('Plans') }}</a>
    <a href="{{ route('app.billing.index', ['tab' => 'addons']) }}" class="rounded-lg px-3 py-1.5 {{ $tab === 'addons' ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100' }}">{{ __('Add-ons') }}</a>
</div>

@if ($tab === 'overview')
    <div class="bg-white rounded-xl border border-slate-100 p-6 mb-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs font-semibold uppercase text-slate-400">{{ __('Plan') }}</p>
                <p class="text-xl font-bold text-slate-900 mt-1">{{ $subscription->plan->name ?? __('No active subscription') }}</p>
                @if ($subscription?->current_period_end)
                    <p class="text-sm text-slate-500 mt-1">{{ __('Renewal date') }}: {{ $subscription->current_period_end->format('Y-m-d') }}</p>
                @endif
            </div>
            <div class="flex items-center gap-3">
                @if ($subscription)
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">{{ $subscription->isTrial() ? __('trialing') : ucfirst($subscription->status) }}</span>
                @endif
                @if ($subscription && ! $subscription->cancelled_at)
                    <form method="POST" action="{{ route('app.billing.cancel') }}" onsubmit="return confirm('{{ __('Cancel your subscription? You will keep access until the end of the current period.') }}')">
                        @csrf
                        <button type="submit" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-500 hover:border-red-300 hover:text-red-600">{{ __('Cancel subscription') }}</button>
                    </form>
                @endif
                <a href="{{ route('app.billing.index', ['tab' => 'plans']) }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Subscribe now') }}</a>
            </div>
        </div>
    </div>

    @if ($usage)
        <div class="bg-white rounded-xl border border-slate-100 p-6 mb-6">
            <h3 class="font-semibold text-slate-900 mb-4">{{ __('Usage') }}</h3>
            <div class="space-y-5">
                @foreach ([
                    'invoices' => __('Invoices this period'),
                    'customers' => __('Customers'),
                    'suppliers' => __('Suppliers'),
                    'users' => __('Users'),
                    'invoice_templates' => __('Invoice templates'),
                    'warehouses' => __('Warehouses'),
                    'bank_accounts' => __('Bank & cash accounts'),
                    'branches' => __('Branches'),
                ] as $key => $label)
                    @php
                        $u = $usage[$key];
                        $pct = $u['limit'] ? min(100, round(($u['used'] / max($u['limit'], 1)) * 100)) : 0;
                    @endphp
                    <div>
                        <div class="flex justify-between text-sm mb-1.5">
                            <span class="text-slate-600">{{ $label }}</span>
                            <span class="text-slate-500">{{ $u['used'] }} {{ __('of') }} {{ $u['limit'] ?? __('Unlimited') }}</span>
                        </div>
                        <div class="h-1.5 rounded-full bg-slate-100 overflow-hidden">
                            @if ($u['limit'])
                                <div class="h-full rounded-full {{ $pct >= 90 ? 'bg-red-500' : 'bg-slate-900' }}" style="width: {{ $pct }}%"></div>
                            @else
                                <div class="h-full rounded-full bg-slate-300" style="width: 100%"></div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="bg-white rounded-xl border border-slate-100 p-6 mb-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-semibold text-slate-900">{{ __('Saved payment methods') }}</h3>
            <button type="button" disabled title="{{ __('Card storage will be available once a live payment gateway is connected.') }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-400 cursor-not-allowed">{{ __('Add a new card') }}</button>
        </div>
        <p class="text-sm text-slate-500 mb-3">{{ __('Cards on file with Stripe for renewals and other charges.') }}</p>
        <div class="rounded-lg border border-dashed border-slate-200 px-6 py-8 text-center text-sm text-slate-400">{{ __('No saved cards yet.') }}</div>
    </div>

    <div class="bg-white rounded-xl border border-slate-100">
        <div class="px-6 py-4 border-b border-slate-100">
            <h2 class="font-semibold text-slate-900">{{ __('Payment history') }}</h2>
        </div>
        @if ($payments->isEmpty())
            <p class="px-6 py-8 text-sm text-slate-500">{{ __('No payments yet.') }}</p>
        @else
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-slate-500 border-b border-slate-100">
                        <th class="px-6 py-3 font-medium">{{ __('Date') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('Plan') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('Amount') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('Status') }}</th>
                        <th class="px-6 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($payments as $payment)
                        <tr class="border-b border-slate-50 last:border-0">
                            <td class="px-6 py-3">{{ optional($payment->paid_at)->format('Y-m-d') ?? '—' }}</td>
                            <td class="px-6 py-3">{{ $payment->plan->name ?? '—' }}</td>
                            <td class="px-6 py-3">SAR {{ number_format($payment->amount, 2) }}</td>
                            <td class="px-6 py-3">{{ ucfirst($payment->status) }}</td>
                            <td class="px-6 py-3 text-right">
                                @if ($payment->status === 'paid')
                                    <a href="{{ route('app.billing.receipt', $payment) }}" class="text-brand-700 hover:underline">{{ __('Download receipt') }}</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
    <div class="mt-4">{{ $payments->links() }}</div>
@elseif ($tab === 'plans')
    @php
        $limitRows = [
            'max_users' => __('Team members'),
            'max_invoices_per_month' => __('Invoices per month'),
            'max_customers' => __('Customers'),
            'max_suppliers' => __('Suppliers'),
            'max_invoice_templates' => __('Invoice templates'),
            'max_warehouses' => __('Warehouses'),
            'max_bank_accounts' => __('Bank & cash accounts'),
            'max_branches' => __('Branches'),
        ];
        $featureRows = [
            'has_recurring_invoices' => __('Recurring invoices'),
            'has_quotations' => __('Quotations & proforma invoices'),
            'has_stamps' => __('Company stamp on documents'),
            'has_financial_statements' => __('Financial statements'),
            'has_vat_return_report' => __('VAT return report'),
            'has_cost_centers' => __('Cost centers'),
            'has_purchase_orders' => __('Purchase orders'),
            'has_debit_notes' => __('Debit notes (purchase returns)'),
            'has_roles_permissions' => __('Custom roles & permissions'),
            'has_zatca_phase2' => __('ZATCA Phase 2 integration'),
        ];
    @endphp
    <div x-data="{ couponCode: '{{ old('coupon_code', '') }}' }">
        <div class="mb-6 bg-white rounded-xl border border-slate-100 p-4 max-w-sm">
            <label class="block text-sm font-medium text-slate-700">{{ __('Have a coupon code?') }}</label>
            <input type="text" x-model="couponCode" placeholder="{{ __('e.g. SAVE20') }}" class="mt-1 w-full rounded-lg border border-slate-200 text-sm uppercase focus:border-brand-500 focus:ring-brand-500">
            @error('coupon_code')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>
    <div class="grid md:grid-cols-3 gap-6">
        @foreach ($plans as $plan)
            <div class="bg-white rounded-xl border {{ $subscription && $subscription->plan_id === $plan->id ? 'border-brand-500 ring-1 ring-brand-500' : 'border-slate-100' }} p-6">
                <h3 class="font-bold text-slate-900">{{ app()->getLocale() === 'ar' && $plan->name_ar ? $plan->name_ar : $plan->name }}</h3>
                @if ($plan->price_monthly_original > $plan->price_monthly)
                    <p class="mt-2 text-sm text-slate-400 line-through">SAR {{ number_format($plan->price_monthly_original, 0) }}</p>
                @endif
                <p class="{{ $plan->price_monthly_original > $plan->price_monthly ? '' : 'mt-2' }} text-2xl font-extrabold text-slate-900">SAR {{ number_format($plan->price_monthly, 0) }}<span class="text-sm font-normal text-slate-500">/{{ __('month') }}</span></p>
                <p class="text-xs text-slate-400">SAR {{ number_format($plan->price_yearly, 0) }}/{{ __('year') }}</p>

                <ul class="mt-4 space-y-1.5 text-xs text-slate-500">
                    @foreach ($limitRows as $field => $label)
                        <li>{{ $label }}: {{ $plan->{$field} ?? __('Unlimited') }}</li>
                    @endforeach
                </ul>
                <ul class="mt-4 space-y-1.5 text-xs text-slate-600 border-t border-slate-100 pt-4">
                    @foreach ($featureRows as $field => $label)
                        <li class="flex items-center gap-1.5">
                            @if ($plan->{$field})
                                <span class="text-brand-600">✓</span> {{ $label }}
                            @else
                                <span class="text-slate-300">—</span> <span class="text-slate-400">{{ $label }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>

                <form method="POST" action="{{ route('app.billing.upgrade') }}" class="mt-4 space-y-2">
                    @csrf
                    <input type="hidden" name="plan_id" value="{{ $plan->id }}">
                    <input type="hidden" name="coupon_code" x-model="couponCode">
                    <select name="billing_cycle" class="w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                        <option value="monthly">{{ __('Monthly') }}</option>
                        <option value="yearly">{{ __('Yearly') }}</option>
                    </select>
                    @if (! empty($enabledProviders))
                        <select name="provider" class="w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                            @foreach ($enabledProviders as $provider)
                                <option value="{{ $provider }}">
                                    {{ $provider === \App\Models\PaymentGateway::BANK_TRANSFER ? __('Pay by bank transfer') : __('Pay online with :provider', ['provider' => ucfirst($provider)]) }}
                                </option>
                            @endforeach
                        </select>
                    @endif
                    <button type="submit" class="w-full rounded-lg {{ $subscription && $subscription->plan_id === $plan->id ? 'bg-slate-100 text-slate-500' : 'bg-brand-600 text-white hover:bg-brand-700' }} px-4 py-2 text-sm font-semibold">
                        {{ $subscription && $subscription->plan_id === $plan->id ? __('Current plan') : __('Choose plan') }}
                    </button>
                </form>
            </div>
        @endforeach
    </div>
    </div>
@else
    <div class="bg-white rounded-xl border border-slate-100 p-6">
        <h3 class="font-semibold text-slate-900 mb-1">{{ __('Active add-ons') }}</h3>
        <p class="text-sm text-slate-500 mb-4">{{ __('Optional extras for your subscription.') }}</p>
        <div class="rounded-lg border border-dashed border-slate-200 px-6 py-10 text-center text-sm text-slate-400">{{ __('No add-ons available yet.') }}</div>
    </div>
@endif
@endsection
