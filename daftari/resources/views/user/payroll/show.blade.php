@extends('layouts.app')

@section('title', __('Payroll run :number', ['number' => $payrollRun->run_number]))

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <div>
        <h1 class="text-lg font-semibold text-slate-900">{{ $payrollRun->run_number }}</h1>
        <p class="text-sm text-slate-500">{{ \Carbon\Carbon::create($payrollRun->period_year, $payrollRun->period_month, 1)->translatedFormat('F Y') }} · {{ __('Pay date') }}: {{ $payrollRun->pay_date->format('Y-m-d') }}</p>
    </div>
    <div class="flex gap-2">
        @if ($payrollRun->status === 'draft')
            <form method="POST" action="{{ route('app.payroll.approve', $payrollRun) }}" onsubmit="return confirm('{{ __('Approve and post this payroll run to the ledger? Salary components can no longer be edited afterward.') }}')">
                @csrf
                <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Approve & post') }}</button>
            </form>
        @endif
        @if ($payrollRun->status === 'approved')
            <form method="POST" action="{{ route('app.payroll.mark-paid', $payrollRun) }}">
                @csrf
                <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">{{ __('Mark as paid') }}</button>
            </form>
            <a href="{{ route('app.payroll.wps', $payrollRun) }}" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download WPS file') }}</a>
        @endif
        @if (in_array($payrollRun->status, ['draft', 'approved']))
            <form method="POST" action="{{ route('app.payroll.cancel', $payrollRun) }}" onsubmit="return confirm('{{ __('Cancel this payroll run?') }}')">
                @csrf
                <button type="submit" class="rounded-lg border border-red-200 bg-white px-4 py-2 text-sm font-semibold text-red-600 hover:border-red-300">{{ __('Cancel run') }}</button>
            </form>
        @endif
    </div>
</div>

<div class="grid grid-cols-2 sm:grid-cols-5 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-slate-100 p-4">
        <p class="text-xs font-semibold uppercase text-slate-400">{{ __('Gross') }}</p>
        <p class="mt-1 text-lg font-semibold text-slate-900 tabular-nums">{{ number_format($payrollRun->total_gross, 2) }}</p>
    </div>
    <div class="bg-white rounded-xl border border-slate-100 p-4">
        <p class="text-xs font-semibold uppercase text-slate-400">{{ __('GOSI (employee)') }}</p>
        <p class="mt-1 text-lg font-semibold text-slate-900 tabular-nums">{{ number_format($payrollRun->total_gosi_employee, 2) }}</p>
    </div>
    <div class="bg-white rounded-xl border border-slate-100 p-4">
        <p class="text-xs font-semibold uppercase text-slate-400">{{ __('GOSI (employer)') }}</p>
        <p class="mt-1 text-lg font-semibold text-slate-900 tabular-nums">{{ number_format($payrollRun->total_gosi_employer, 2) }}</p>
    </div>
    <div class="bg-white rounded-xl border border-slate-100 p-4">
        <p class="text-xs font-semibold uppercase text-slate-400">{{ __('Other deductions') }}</p>
        <p class="mt-1 text-lg font-semibold text-slate-900 tabular-nums">{{ number_format($payrollRun->total_other_deductions, 2) }}</p>
    </div>
    <div class="bg-white rounded-xl border border-slate-100 p-4">
        <p class="text-xs font-semibold uppercase text-slate-400">{{ __('Net pay') }}</p>
        <p class="mt-1 text-lg font-semibold text-brand-700 tabular-nums">{{ number_format($payrollRun->total_net, 2) }}</p>
    </div>
</div>

<div class="bg-white rounded-xl border border-slate-100 overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-slate-500 border-b border-slate-100">
                <th class="px-4 py-3 font-medium">{{ __('Employee') }}</th>
                <th class="px-4 py-3 font-medium text-end">{{ __('Basic') }}</th>
                <th class="px-4 py-3 font-medium text-end">{{ __('Allowances') }}</th>
                <th class="px-4 py-3 font-medium text-end">{{ __('Gross') }}</th>
                <th class="px-4 py-3 font-medium text-end">{{ __('GOSI') }}</th>
                <th class="px-4 py-3 font-medium text-end">{{ __('Other deductions') }}</th>
                <th class="px-4 py-3 font-medium text-end">{{ __('Net') }}</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($payrollRun->items as $item)
                <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50">
                    <td class="px-4 py-3">
                        <p class="font-medium text-slate-800">{{ $item->employee->full_name }}</p>
                        <p class="text-xs text-slate-400">{{ $item->employee->employee_number }}</p>
                    </td>
                    <td class="px-4 py-3 text-end tabular-nums">{{ number_format($item->basic_salary, 2) }}</td>
                    <td class="px-4 py-3 text-end tabular-nums">{{ number_format($item->housing_allowance + $item->transport_allowance + $item->other_allowance, 2) }}</td>
                    <td class="px-4 py-3 text-end tabular-nums">{{ number_format($item->gross_salary, 2) }}</td>
                    <td class="px-4 py-3 text-end tabular-nums">{{ number_format($item->gosi_employee_contribution, 2) }}</td>
                    <td class="px-4 py-3 text-end">
                        @if ($payrollRun->status === 'draft')
                            <form method="POST" action="{{ route('app.payroll.items.update', [$payrollRun, $item]) }}" class="flex items-center justify-end gap-2">
                                @csrf
                                <input type="number" step="0.01" min="0" name="other_deductions" value="{{ $item->other_deductions }}" class="w-24 rounded-lg border border-slate-200 text-sm text-end focus:border-brand-500 focus:ring-brand-500">
                                <input type="hidden" name="days_worked" value="{{ $item->days_worked }}">
                                <button type="submit" class="text-xs text-brand-700 hover:underline">{{ __('Save') }}</button>
                            </form>
                        @else
                            <span class="tabular-nums">{{ number_format($item->other_deductions, 2) }}</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-end font-semibold text-slate-900 tabular-nums">{{ number_format($item->net_salary, 2) }}</td>
                    <td class="px-4 py-3 text-end">
                        @if ($payrollRun->status !== 'draft')
                            <a href="{{ route('app.payroll.items.payslip', [$payrollRun, $item]) }}" class="text-xs text-brand-700 hover:underline">{{ __('Payslip') }}</a>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
