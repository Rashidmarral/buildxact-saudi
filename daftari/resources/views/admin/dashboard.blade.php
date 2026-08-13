@extends('layouts.admin')

@section('title', __('Overview'))

@section('content')
<div class="grid sm:grid-cols-2 lg:grid-cols-5 gap-5">
    @foreach ([
        [__('MRR'), 'SAR '.number_format($stats['mrr'], 0), '💰'],
        [__('Revenue this month'), 'SAR '.number_format($stats['revenue_this_month'], 2), '📈'],
        [__('Total companies'), $stats['total_companies'], '🏢'],
        [__('Active companies'), $stats['active_companies'], '✅'],
        [__('Trialing companies'), $stats['trialing_companies'], '⏳'],
    ] as [$label, $value, $icon])
        <div class="bg-white rounded-xl border border-slate-100 p-5">
            <div class="flex items-center justify-between">
                <span class="text-sm text-slate-500">{{ $label }}</span>
                <span>{{ $icon }}</span>
            </div>
            <div class="mt-2 text-2xl font-bold text-slate-900">{{ $value }}</div>
        </div>
    @endforeach
</div>

<div class="mt-8 bg-white rounded-xl border border-slate-100">
    <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
        <h2 class="font-semibold text-slate-900">{{ __('Recent companies') }}</h2>
        <a href="{{ route('admin.companies.index') }}" class="text-sm font-semibold text-brand-700 hover:underline">{{ __('View all') }}</a>
    </div>
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-slate-500 border-b border-slate-100">
                <th class="px-6 py-3 font-medium">{{ __('Company') }}</th>
                <th class="px-6 py-3 font-medium">{{ __('Status') }}</th>
                <th class="px-6 py-3 font-medium">{{ __('Signed up') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($recentCompanies as $company)
                <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50 cursor-pointer" onclick="window.location='{{ route('admin.companies.show', $company) }}'">
                    <td class="px-6 py-3 font-medium text-brand-700">{{ $company->name }}</td>
                    <td class="px-6 py-3">{{ $company->status === 'active' ? __('Active') : __('Suspended') }}</td>
                    <td class="px-6 py-3">{{ $company->created_at->format('Y-m-d') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
