@extends('layouts.app')

@section('title', __('Operators'))

@section('content')
<div class="flex items-center justify-between mb-6">
    <p class="text-sm text-slate-500">{{ __('Equipment operators/drivers — selectable on machinery assets and rental contracts.') }}</p>
    <a href="{{ route('app.machinery.operators.create') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('+ Add operator') }}</a>
</div>

<div class="bg-white rounded-xl border border-slate-100">
    @if ($operators->isEmpty())
        <p class="px-6 py-8 text-sm text-slate-500">{{ __('No operators yet.') }}</p>
    @else
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="px-6 py-3 font-medium">{{ __('No.') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Name') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Mobile') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('License number') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('License expiry') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Status') }}</th>
                    <th class="px-6 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($operators as $operator)
                    <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50">
                        <td class="px-6 py-3 text-slate-500">{{ $operator->employee_number }}</td>
                        <td class="px-6 py-3 font-medium text-slate-800">
                            {{ $operator->full_name }}
                            @if ($operator->full_name_ar)<span class="block text-xs text-slate-400" dir="rtl">{{ $operator->full_name_ar }}</span>@endif
                        </td>
                        <td class="px-6 py-3 text-slate-500">{{ $operator->mobile ?: '—' }}</td>
                        <td class="px-6 py-3 text-slate-500">{{ $operator->license_number ?: '—' }}</td>
                        <td class="px-6 py-3 text-slate-500">
                            @if ($operator->license_expiry_date)
                                <span class="{{ $operator->license_expiry_date->isPast() ? 'text-red-600 font-semibold' : '' }}">{{ $operator->license_expiry_date->format('Y-m-d') }}</span>
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-6 py-3">
                            @if ($operator->status === 'active')
                                <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700">{{ __('Active') }}</span>
                            @else
                                <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-600">{{ __('Inactive') }}</span>
                            @endif
                        </td>
                        <td class="px-6 py-3 text-right">
                            <a href="{{ route('app.employees.edit', $operator) }}" class="text-brand-700 hover:underline">{{ __('Edit full HR details') }}</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

<div class="mt-4">@include('partials.pagination', ['paginator' => $operators])</div>
@endsection
