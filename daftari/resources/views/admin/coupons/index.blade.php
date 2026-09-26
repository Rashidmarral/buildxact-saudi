@extends('layouts.admin')

@section('title', __('Coupons'))

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <form method="GET" class="flex flex-wrap items-end gap-3">
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Code') }}</label>
            <input type="text" name="q" value="{{ request('q') }}" placeholder="{{ __('Search by code...') }}" class="rounded-lg border border-slate-200 text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Status') }}</label>
            <select name="status" class="rounded-lg border border-slate-200 text-sm">
                <option value="">{{ __('All statuses') }}</option>
                <option value="active" @selected(request('status') === 'active')>{{ __('Active') }}</option>
                <option value="inactive" @selected(request('status') === 'inactive')>{{ __('Inactive') }}</option>
                <option value="expired" @selected(request('status') === 'expired')>{{ __('Expired') }}</option>
            </select>
        </div>
        <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Filter') }}</button>
    </form>
    <a href="{{ route('admin.coupons.create') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('+ New coupon') }}</a>
</div>

<div class="bg-white rounded-xl border border-slate-100 overflow-x-auto">
    @if ($coupons->isEmpty())
        <p class="px-6 py-8 text-sm text-slate-500">{{ __('No coupons yet.') }}</p>
    @else
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100 whitespace-nowrap">
                    <th class="px-6 py-3 font-medium">{{ __('Code') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Discount type') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Discount') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Uses') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Revenue discounted') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Expires') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Status') }}</th>
                    <th class="px-6 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($coupons as $coupon)
                    <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50">
                        <td class="px-6 py-3 font-mono font-semibold text-slate-800">{{ $coupon->code }}</td>
                        <td class="px-4 py-3">{{ ucfirst(str_replace('_', ' ', $coupon->discount_type)) }}</td>
                        <td class="px-4 py-3">{{ $coupon->percentage !== null ? number_format($coupon->percentage, 0).'%' : $coupon->currency.' '.number_format($coupon->fixed_amount, 2) }}</td>
                        <td class="px-4 py-3">{{ $coupon->redemptions_count }}{{ $coupon->max_uses ? ' / '.$coupon->max_uses : '' }}</td>
                        <td class="px-4 py-3">{{ $coupon->currency }} {{ number_format($coupon->redemptions_sum_discount_amount ?? 0, 2) }}</td>
                        <td class="px-4 py-3">{{ optional($coupon->expires_at)->format('Y-m-d') ?? '—' }}</td>
                        <td class="px-4 py-3">
                            @if (! $coupon->is_active)
                                <span class="text-slate-400">{{ __('Inactive') }}</span>
                            @elseif ($coupon->isExpired())
                                <span class="text-red-600">{{ __('Expired') }}</span>
                            @else
                                <span class="text-emerald-700">{{ __('Active') }}</span>
                            @endif
                        </td>
                        <td class="px-6 py-3 text-right whitespace-nowrap space-x-3 rtl:space-x-reverse">
                            <a href="{{ route('admin.coupons.show', $coupon) }}" class="text-brand-700 hover:underline">{{ __('View') }}</a>
                            <a href="{{ route('admin.coupons.edit', $coupon) }}" class="text-brand-700 hover:underline">{{ __('Edit') }}</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

<div class="mt-4">{{ $coupons->links() }}</div>
@endsection
