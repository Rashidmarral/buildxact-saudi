@extends('layouts.app')

@section('title', __('Hired-in Equipment'))

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-xl font-bold text-slate-900">{{ __('Hired-in Equipment') }}</h1>
        <p class="text-sm text-slate-500 mt-1">{{ __('Equipment hired in from external suppliers — not owned by your company.') }}</p>
    </div>
    <a href="{{ route('app.machinery.hire-in-contracts.create') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('+ New hire-in contract') }}</a>
</div>

<div class="bg-white rounded-xl border border-slate-100">
    @if ($contracts->isEmpty())
        <p class="px-6 py-8 text-sm text-slate-500">{{ __('No hire-in contracts yet.') }}</p>
    @else
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="px-6 py-3 font-medium">{{ __('Contract') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Equipment') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Supplier') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Period') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Rate') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Status') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($contracts as $contract)
                    <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50 cursor-pointer" onclick="window.location='{{ route('app.machinery.hire-in-contracts.show', $contract) }}'">
                        <td class="px-6 py-3 text-slate-500">{{ $contract->contract_number }}</td>
                        <td class="px-6 py-3 font-medium text-slate-900">{{ $contract->equipment_description }}</td>
                        <td class="px-6 py-3">{{ $contract->supplierDisplayName() }}</td>
                        <td class="px-6 py-3 text-slate-500">{{ $contract->start_date->format('Y-m-d') }} — {{ $contract->end_date?->format('Y-m-d') ?? __('ongoing') }}</td>
                        <td class="px-6 py-3">
                            {{ \App\Support\Money::format($contract->rate) }}
                            @if ($contract->rate_type === 'per_unit')
                                / {{ $contract->rate_unit_label }}
                            @else
                                / {{ __(ucfirst($contract->rate_type)) }}
                            @endif
                        </td>
                        <td class="px-6 py-3">
                            <span class="inline-block rounded-full {{ $contract->status === 'active' ? 'bg-sky-50 text-sky-700' : 'bg-slate-100 text-slate-600' }} text-xs font-medium px-2.5 py-1">{{ ucfirst($contract->status) }}</span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

<div class="mt-4">@include('partials.pagination', ['paginator' => $contracts])</div>
@endsection
