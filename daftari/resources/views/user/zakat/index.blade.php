@extends('layouts.app')

@section('title', __('Zakat Estimate'))

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-xl font-bold text-slate-900">{{ __('Zakat Estimate') }}</h1>
        <p class="text-sm text-slate-500 mt-1">{{ __('An internal planning estimate — not a substitute for your official Zakat return filed with ZATCA.') }}</p>
    </div>
    <a href="{{ route('app.zakat.create') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('New estimate') }}</a>
</div>

<div class="bg-white rounded-xl border border-slate-100">
    @if ($calculations->isEmpty())
        <p class="px-6 py-8 text-sm text-slate-500">{{ __('No Zakat estimates yet.') }}</p>
    @else
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="px-6 py-3 font-medium">{{ __('Period ending') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Zakat base') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Zakat due') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Created') }}</th>
                    <th class="px-6 py-3 font-medium"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($calculations as $calculation)
                    <tr class="border-b border-slate-50 last:border-0">
                        <td class="px-6 py-3">{{ $calculation->period_end_date->format('Y-m-d') }}</td>
                        <td class="px-6 py-3">SAR {{ number_format($calculation->zakat_base, 2) }}</td>
                        <td class="px-6 py-3 font-semibold text-slate-900">SAR {{ number_format($calculation->zakat_due, 2) }}</td>
                        <td class="px-6 py-3 text-slate-500">{{ $calculation->created_at->format('Y-m-d') }}</td>
                        <td class="px-6 py-3 text-right">
                            <a href="{{ route('app.zakat.show', $calculation) }}" class="text-xs font-semibold text-brand-700 hover:underline">{{ __('View') }}</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

<div class="mt-4">{{ $calculations->links() }}</div>
@endsection
