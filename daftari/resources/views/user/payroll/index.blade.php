@extends('layouts.app')

@section('title', __('Payroll'))

@section('content')
<div class="flex items-center justify-between mb-6">
    <p class="text-sm text-slate-500">{{ __('Run payroll each pay period and post it to the ledger.') }}</p>
    <div class="flex gap-2">
        <a href="{{ route('app.employees.index') }}" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Employees') }}</a>
        <a href="{{ route('app.payroll.create') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('+ Run payroll') }}</a>
    </div>
</div>

<div class="bg-white rounded-xl border border-slate-100">
    @if ($payrollRuns->isEmpty())
        <p class="px-6 py-8 text-sm text-slate-500">{{ __('No payroll runs yet.') }}</p>
    @else
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="px-6 py-3 font-medium">{{ __('Run') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Period') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Pay date') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Net total') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Status') }}</th>
                    <th class="px-6 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($payrollRuns as $run)
                    <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50">
                        <td class="px-6 py-3 font-medium text-slate-800">{{ $run->run_number }}</td>
                        <td class="px-6 py-3 text-slate-500">{{ \Carbon\Carbon::create($run->period_year, $run->period_month, 1)->translatedFormat('F Y') }}</td>
                        <td class="px-6 py-3 text-slate-500">{{ $run->pay_date->format('Y-m-d') }}</td>
                        <td class="px-6 py-3 text-slate-500 tabular-nums">{{ number_format($run->total_net, 2) }}</td>
                        <td class="px-6 py-3">
                            @php
                                $statusStyles = [
                                    'draft' => 'bg-slate-100 text-slate-600',
                                    'approved' => 'bg-blue-50 text-blue-700',
                                    'paid' => 'bg-emerald-50 text-emerald-700',
                                    'cancelled' => 'bg-red-50 text-red-700',
                                ];
                            @endphp
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $statusStyles[$run->status] ?? 'bg-slate-100 text-slate-600' }}">
                                {{ ucfirst($run->status) }}
                            </span>
                        </td>
                        <td class="px-6 py-3 text-right">
                            <a href="{{ route('app.payroll.show', $run) }}" class="text-brand-700 hover:underline">{{ __('View') }}</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

<div class="mt-4">{{ $payrollRuns->links() }}</div>
@endsection
