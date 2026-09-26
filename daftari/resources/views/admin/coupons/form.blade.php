@extends('layouts.admin')

@section('title', $coupon->exists ? __('Edit Coupon') : __('New Coupon'))

@section('content')
<form method="POST" action="{{ $coupon->exists ? route('admin.coupons.update', $coupon) : route('admin.coupons.store') }}" class="max-w-3xl space-y-6">
    @csrf
    @if ($coupon->exists) @method('PUT') @endif

    <div class="bg-white rounded-xl border border-slate-100 p-6 space-y-5">
        <h2 class="font-semibold text-slate-900">{{ __('Coupon details') }}</h2>
        <div class="grid sm:grid-cols-2 gap-5">
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Coupon code') }}</label>
                <input type="text" name="code" value="{{ old('code', $coupon->code) }}" required class="mt-1 w-full rounded-lg border border-slate-200 font-mono uppercase focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Discount type') }}</label>
                <select name="discount_type" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                    @foreach ([
                        'percentage' => __('Percentage discount'),
                        'fixed' => __('Fixed amount discount'),
                        'first_payment' => __('First payment discount'),
                        'recurring' => __('Recurring discount'),
                        'limited_duration' => __('Limited-duration discount'),
                    ] as $value => $label)
                        <option value="{{ $value }}" @selected(old('discount_type', $coupon->discount_type) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-slate-700">{{ __('Description') }}</label>
                <textarea name="description" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">{{ old('description', $coupon->description) }}</textarea>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Percentage (%)') }}</label>
                <input type="number" step="0.01" min="0.01" max="100" name="percentage" value="{{ old('percentage', $coupon->percentage) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Fixed amount') }}</label>
                <input type="number" step="0.01" min="0.01" name="fixed_amount" value="{{ old('fixed_amount', $coupon->fixed_amount) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
            <p class="text-xs text-slate-400 sm:col-span-2 -mt-2">{{ __('Set exactly one of Percentage or Fixed amount — never both.') }}</p>
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Currency') }}</label>
                <select name="currency" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                    @foreach (\App\Models\Currency::query()->active()->orderBy('sort_order')->pluck('code') as $code)
                        <option value="{{ $code }}" @selected(old('currency', $coupon->currency ?? 'SAR') === $code)>{{ $code }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Cycles (limited-duration only)') }}</label>
                <input type="number" min="1" name="duration_in_cycles" value="{{ old('duration_in_cycles', $coupon->duration_in_cycles) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Start date') }}</label>
                <input type="date" name="starts_at" value="{{ old('starts_at', optional($coupon->starts_at)->format('Y-m-d')) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Expiry date') }}</label>
                <input type="date" name="expires_at" value="{{ old('expires_at', optional($coupon->expires_at)->format('Y-m-d')) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Maximum uses') }}</label>
                <input type="number" min="1" name="max_uses" value="{{ old('max_uses', $coupon->max_uses) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                <p class="text-xs text-slate-400 mt-1">{{ __('Leave blank for unlimited.') }}</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Maximum uses per company') }}</label>
                <input type="number" min="1" name="max_uses_per_company" value="{{ old('max_uses_per_company', $coupon->max_uses_per_company) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                <p class="text-xs text-slate-400 mt-1">{{ __('Leave blank for unlimited.') }}</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Minimum subscription amount') }}</label>
                <input type="number" step="0.01" min="0" name="min_subscription_amount" value="{{ old('min_subscription_amount', $coupon->min_subscription_amount) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
        </div>

        <div class="space-y-2 pt-2 border-t border-slate-100">
            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $coupon->is_active ?? true)) class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                {{ __('Active') }}
            </label>
            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" name="new_customers_only" value="1" @checked(old('new_customers_only', $coupon->new_customers_only ?? false)) class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                {{ __('New customers only') }}
            </label>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-100 p-6 space-y-3">
        <div>
            <h2 class="font-semibold text-slate-900">{{ __('Applicable plans') }}</h2>
            <p class="text-sm text-slate-500 mt-1">{{ __('Leave every plan unchecked to apply this coupon to all plans.') }}</p>
        </div>
        <div class="grid sm:grid-cols-3 gap-2">
            @php $selectedPlanIds = old('plan_ids', $coupon->exists ? $coupon->plans->pluck('id')->all() : []); @endphp
            @foreach ($plans as $plan)
                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <input type="checkbox" name="plan_ids[]" value="{{ $plan->id }}" @checked(in_array($plan->id, $selectedPlanIds)) class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                    {{ $plan->name }}
                </label>
            @endforeach
        </div>
    </div>

    <div class="flex gap-3">
        <button type="submit" class="rounded-lg bg-brand-600 px-6 py-2.5 font-semibold text-white hover:bg-brand-700">{{ __('Save') }}</button>
        <a href="{{ route('admin.coupons.index') }}" class="rounded-lg border border-slate-200 px-6 py-2.5 font-semibold text-slate-600 hover:border-slate-300">{{ __('Cancel') }}</a>
    </div>
</form>
@endsection
