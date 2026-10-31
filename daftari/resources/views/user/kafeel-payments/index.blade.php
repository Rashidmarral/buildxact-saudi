@extends('layouts.app')

@section('title', __('Kafeel Payments'))

@section('content')
<div class="flex items-start justify-between mb-6">
    <div>
        <h2 class="text-lg font-semibold text-slate-900">{{ __('Kafeel Payments') }}</h2>
        <p class="text-sm text-slate-500 mt-1">{{ __('A private log of payments made to or on behalf of your Kafeel (sponsor) — license fees, insurance, fines, cash handed to staff, subscriptions, and anything else. Not posted to your company accounts.') }}</p>
    </div>
    <div class="flex items-center gap-2 shrink-0">
        <a href="{{ route('app.kafeel-payments.import.form') }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Import CSV') }}</a>
        <a href="{{ route('app.kafeel-payments.create') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('+ Record a payment') }}</a>
    </div>
</div>

@if (session('importSkipped'))
    <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 mb-6">
        <p class="font-semibold mb-1">{{ __('Some rows were skipped:') }}</p>
        <ul class="list-disc ps-5 space-y-0.5">
            @foreach (session('importSkipped') as $reason)
                <li>{{ $reason }}</li>
            @endforeach
        </ul>
    </div>
@endif

@include('user.reports.partials.period-selector', ['period' => $period])

<div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-slate-100 p-4">
        <p class="text-xs text-slate-400">{{ __('This period') }}</p>
        <p class="text-lg font-bold text-slate-900 mt-1">{{ \App\Support\Money::format($total) }}</p>
        <p class="text-xs text-slate-400 mt-0.5">{{ trans_choice(':count payment|:count payments', $count, ['count' => $count]) }}</p>
    </div>
    <div class="bg-white rounded-xl border border-slate-100 p-4">
        <p class="text-xs text-slate-400">{{ __('All-time total') }}</p>
        <p class="text-lg font-bold text-slate-900 mt-1">{{ \App\Support\Money::format($allTimeTotal) }}</p>
    </div>
    @foreach ($byCategory->take(2) as $category => $amount)
        <div class="bg-white rounded-xl border border-slate-100 p-4">
            <p class="text-xs text-slate-400">{{ __(\App\Models\KafeelPayment::CATEGORIES[$category] ?? $category) }}</p>
            <p class="text-lg font-bold text-slate-900 mt-1">{{ \App\Support\Money::format($amount) }}</p>
        </div>
    @endforeach
</div>

@if ($byCategory->isNotEmpty())
    <div class="flex flex-wrap gap-2 mb-6">
        @foreach ($byCategory as $category => $amount)
            <a href="{{ route('app.kafeel-payments.index', array_merge(request()->query(), ['category' => $category])) }}" class="inline-flex items-center gap-2 rounded-full {{ request()->query('category') === $category ? 'bg-brand-600 text-white' : 'bg-slate-100 text-slate-600' }} px-3 py-1 text-xs">
                {{ __(\App\Models\KafeelPayment::CATEGORIES[$category] ?? $category) }}
                <span class="font-semibold">{{ \App\Support\Money::format($amount) }}</span>
            </a>
        @endforeach
        @if (request()->filled('category'))
            <a href="{{ route('app.kafeel-payments.index', array_merge(request()->except('category'))) }}" class="inline-flex items-center rounded-full border border-slate-200 px-3 py-1 text-xs text-slate-500 hover:border-slate-300">{{ __('Clear filter') }}</a>
        @endif
    </div>
@endif

<form method="GET" class="flex items-center gap-2 mb-4">
    @foreach (request()->except('search', 'page') as $name => $value)
        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
    @endforeach
    <input type="search" name="search" value="{{ request()->query('search') }}" placeholder="{{ __('Search description or recipient…') }}" class="w-72 rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
    <button type="submit" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:border-slate-300">{{ __('Search') }}</button>
    <div class="ms-auto flex items-center gap-2">
        <a href="{{ route('app.kafeel-payments.index', array_merge(request()->query(), ['export' => 'csv'])) }}" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:border-slate-300">{{ __('Download CSV') }}</a>
        <a href="{{ route('app.kafeel-payments.index', array_merge(request()->query(), ['export' => 'pdf'])) }}" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:border-slate-300">{{ __('Download PDF') }}</a>
    </div>
</form>

<div class="bg-white rounded-xl border border-slate-100 overflow-x-auto">
    @if ($payments->isEmpty())
        <p class="px-6 py-10 text-center text-sm text-slate-400">{{ __('No Kafeel payments in this period.') }}</p>
    @else
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="px-5 py-3 font-medium">{{ __('Date') }}</th>
                    <th class="px-5 py-3 font-medium">{{ __('Paid to') }}</th>
                    <th class="px-5 py-3 font-medium">{{ __('Category') }}</th>
                    <th class="px-5 py-3 font-medium">{{ __('Description') }}</th>
                    <th class="px-5 py-3 font-medium text-end">{{ __('Amount') }}</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($payments as $payment)
                    <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50 cursor-pointer" onclick="window.location='{{ route('app.kafeel-payments.edit', $payment) }}'">
                        <td class="px-5 py-3">{{ $payment->payment_date->format('Y-m-d') }}</td>
                        <td class="px-5 py-3">{{ $payment->paid_to ?: '—' }}</td>
                        <td class="px-5 py-3"><span class="inline-flex rounded-full bg-slate-100 px-2.5 py-0.5 text-xs text-slate-600">{{ $payment->categoryLabel() }}</span></td>
                        <td class="px-5 py-3 text-slate-700">{{ $payment->description }}</td>
                        <td class="px-5 py-3 text-end font-semibold tabular-nums">{{ \App\Support\Money::format($payment->amount) }}</td>
                        <td class="px-5 py-3 text-end text-xs text-slate-400">{{ __('By') }} {{ $payment->creator?->name ?: '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

<div class="mt-4">@include('partials.pagination', ['paginator' => $payments])</div>
@endsection
