@extends('layouts.admin')

@section('title', $coupon->code)

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <div>
        <div class="flex items-center gap-2">
            <h2 class="text-lg font-mono font-semibold text-slate-900">{{ $coupon->code }}</h2>
            @if (! $coupon->is_active)
                <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-semibold bg-slate-100 text-slate-500">{{ __('Inactive') }}</span>
            @elseif ($coupon->isExpired())
                <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-semibold bg-red-50 text-red-600">{{ __('Expired') }}</span>
            @else
                <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-semibold bg-emerald-50 text-emerald-700">{{ __('Active') }}</span>
            @endif
        </div>
        <p class="text-sm text-slate-500">{{ $coupon->description ?: __('No description') }}</p>
    </div>
    <a href="{{ route('admin.coupons.edit', $coupon) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Edit') }}</a>
</div>

<div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-slate-100 p-5">
        <p class="text-xs font-semibold uppercase text-slate-500">{{ __('Total uses') }}</p>
        <p class="mt-1 text-2xl font-bold text-slate-900">{{ $stats['total_uses'] }}</p>
    </div>
    <div class="bg-white rounded-xl border border-slate-100 p-5">
        <p class="text-xs font-semibold uppercase text-slate-500">{{ __('Remaining uses') }}</p>
        <p class="mt-1 text-2xl font-bold text-slate-900">{{ $stats['remaining_uses'] ?? __('Unlimited') }}</p>
    </div>
    <div class="bg-white rounded-xl border border-slate-100 p-5">
        <p class="text-xs font-semibold uppercase text-slate-500">{{ __('Revenue discounted') }}</p>
        <p class="mt-1 text-2xl font-bold text-slate-900">{{ $coupon->currency }} {{ number_format($stats['revenue_discounted'], 2) }}</p>
    </div>
    <div class="bg-white rounded-xl border border-slate-100 p-5">
        <p class="text-xs font-semibold uppercase text-slate-500">{{ __('Companies using coupon') }}</p>
        <p class="mt-1 text-2xl font-bold text-slate-900">{{ $stats['companies_using'] }}</p>
    </div>
</div>

<div class="grid md:grid-cols-2 gap-6 mb-6">
    <div class="bg-white rounded-xl border border-slate-100 p-6">
        <h3 class="font-semibold text-slate-900 mb-4">{{ __('Coupon details') }}</h3>
        <dl class="space-y-2 text-sm">
            <div class="flex justify-between"><dt class="text-slate-500">{{ __('Discount type') }}</dt><dd class="font-medium">{{ ucfirst(str_replace('_', ' ', $coupon->discount_type)) }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">{{ __('Discount') }}</dt><dd class="font-medium">{{ $coupon->percentage !== null ? number_format($coupon->percentage, 0).'%' : $coupon->currency.' '.number_format($coupon->fixed_amount, 2) }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">{{ __('Start date') }}</dt><dd class="font-medium">{{ optional($coupon->starts_at)->format('Y-m-d') ?? '—' }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">{{ __('Expiry date') }}</dt><dd class="font-medium">{{ optional($coupon->expires_at)->format('Y-m-d') ?? '—' }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">{{ __('Maximum uses') }}</dt><dd class="font-medium">{{ $coupon->max_uses ?? __('Unlimited') }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">{{ __('Maximum uses per company') }}</dt><dd class="font-medium">{{ $coupon->max_uses_per_company ?? __('Unlimited') }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">{{ __('Minimum subscription amount') }}</dt><dd class="font-medium">{{ $coupon->min_subscription_amount ? $coupon->currency.' '.number_format($coupon->min_subscription_amount, 2) : '—' }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">{{ __('New customers only') }}</dt><dd class="font-medium">{{ $coupon->new_customers_only ? __('Yes') : __('No') }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">{{ __('Applicable plans') }}</dt><dd class="font-medium">{{ $coupon->plans->isEmpty() ? __('All plans') : $coupon->plans->pluck('name')->implode(', ') }}</dd></div>
        </dl>
    </div>

    <div class="bg-white rounded-xl border border-slate-100">
        <div class="px-6 py-4 border-b border-slate-100"><h3 class="font-semibold text-slate-900">{{ __('Audit history') }}</h3></div>
        @if ($auditLogs->isEmpty())
            <p class="px-6 py-8 text-sm text-slate-500">{{ __('No admin actions recorded on this coupon.') }}</p>
        @else
            <ul class="divide-y divide-slate-50 max-h-80 overflow-y-auto">
                @foreach ($auditLogs as $log)
                    <li class="px-6 py-3 text-sm flex items-center justify-between gap-4">
                        <span class="text-slate-700">{{ $log->description ?? $log->action }}</span>
                        <span class="text-slate-400 text-xs whitespace-nowrap">{{ $log->admin?->name ?? __('System') }} · {{ $log->created_at->format('Y-m-d H:i') }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>

<div class="bg-white rounded-xl border border-slate-100">
    <div class="px-6 py-4 border-b border-slate-100"><h3 class="font-semibold text-slate-900">{{ __('Companies using this coupon') }}</h3></div>
    @if ($coupon->redemptions->isEmpty())
        <p class="px-6 py-8 text-sm text-slate-500">{{ __('This coupon has not been used yet.') }}</p>
    @else
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="px-6 py-3 font-medium">{{ __('Company') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Original amount') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Discount') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Final amount') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Redeemed at') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($coupon->redemptions as $redemption)
                    <tr class="border-b border-slate-50 last:border-0">
                        <td class="px-6 py-3">
                            @if ($redemption->company)
                                <a href="{{ route('admin.companies.show', $redemption->company_id) }}" class="text-brand-700 hover:underline">{{ $redemption->company->name }}</a>
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-3">{{ number_format($redemption->original_amount, 2) }}</td>
                        <td class="px-4 py-3 text-orange-600">-{{ number_format($redemption->discount_amount, 2) }}</td>
                        <td class="px-4 py-3 font-medium">{{ number_format($redemption->final_amount, 2) }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $redemption->redeemed_at->format('Y-m-d H:i') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
@endsection
