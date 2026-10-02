@extends('layouts.app')

@section('title', __('Employees'))

@section('content')
<div class="flex items-center justify-between mb-6">
    <p class="text-sm text-slate-500">{{ __('Manage employee records used to run payroll.') }}</p>
    <a href="{{ route('app.employees.create') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('+ New employee') }}</a>
</div>

<div class="bg-white rounded-xl border border-slate-100">
    @if ($employees->isEmpty())
        <p class="px-6 py-8 text-sm text-slate-500">{{ __('No employees yet.') }}</p>
    @else
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="px-6 py-3 font-medium">{{ __('No.') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Name') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Job title') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Nationality') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Gross salary') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Status') }}</th>
                    <th class="px-6 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($employees as $employee)
                    <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50">
                        <td class="px-6 py-3 text-slate-500">{{ $employee->employee_number }}</td>
                        <td class="px-6 py-3 font-medium text-slate-800">{{ $employee->full_name }}</td>
                        <td class="px-6 py-3 text-slate-500">{{ $employee->job_title ?: '—' }}</td>
                        <td class="px-6 py-3 text-slate-500">{{ $employee->is_saudi ? __('Saudi') : ($employee->nationality ?: __('Non-Saudi')) }}</td>
                        <td class="px-6 py-3 text-slate-500 tabular-nums">{{ number_format($employee->grossSalary(), 2) }}</td>
                        <td class="px-6 py-3">
                            @if ($employee->status === 'active')
                                <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700">{{ __('Active') }}</span>
                            @elseif ($employee->status === 'terminated')
                                <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-600">{{ __('Terminated') }}</span>
                            @else
                                <span class="rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-700">{{ __('Suspended') }}</span>
                            @endif
                        </td>
                        <td class="px-6 py-3 text-right space-x-3 rtl:space-x-reverse">
                            <a href="{{ route('app.employees.edit', $employee) }}" class="text-brand-700 hover:underline">{{ __('Edit') }}</a>
                            @if ($employee->status === 'active')
                                <button type="button" onclick="document.getElementById('terminate-{{ $employee->id }}').showModal()" class="text-amber-700 hover:underline">{{ __('Terminate') }}</button>
                            @endif
                        </td>
                    </tr>

                    @if ($employee->status === 'active')
                        <dialog id="terminate-{{ $employee->id }}" class="rounded-xl border border-slate-200 p-0 backdrop:bg-slate-900/40">
                            <form method="POST" action="{{ route('app.employees.terminate', $employee) }}" class="p-6 space-y-4 w-80">
                                @csrf
                                <h3 class="font-semibold text-slate-900">{{ __('Terminate :name', ['name' => $employee->full_name]) }}</h3>
                                <div>
                                    <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Termination date') }}</label>
                                    <input type="date" name="termination_date" required min="{{ $employee->hire_date->toDateString() }}" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Reason') }}</label>
                                    <select name="reason" required class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                                        <option value="resignation">{{ __('Resignation') }}</option>
                                        <option value="termination">{{ __('Termination') }}</option>
                                        <option value="end_of_contract">{{ __('End of contract') }}</option>
                                        <option value="retirement">{{ __('Retirement') }}</option>
                                        <option value="death">{{ __('Death') }}</option>
                                        <option value="disability">{{ __('Disability') }}</option>
                                    </select>
                                </div>
                                <p class="text-xs text-slate-400">{{ __('End-of-service gratuity is calculated automatically based on the reason and years of service.') }}</p>
                                <div class="flex justify-end gap-2">
                                    <button type="button" onclick="this.closest('dialog').close()" class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm font-semibold text-slate-600">{{ __('Cancel') }}</button>
                                    <button type="submit" class="rounded-lg bg-amber-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-amber-700">{{ __('Terminate') }}</button>
                                </div>
                            </form>
                        </dialog>
                    @endif
                @endforeach
            </tbody>
        </table>
    @endif
</div>

<div class="mt-4">{{ $employees->links() }}</div>
@endsection
