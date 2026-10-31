@extends('layouts.app')

@section('title', __('Run Payroll'))

@section('content')
<div class="max-w-lg">
    @if ($activeEmployeeCount === 0)
        <div class="bg-white rounded-xl border border-slate-100 p-6">
            <p class="text-sm text-slate-500">{{ __('There are no active employees to run payroll for.') }}</p>
            <a href="{{ route('app.employees.create') }}" class="mt-4 inline-block rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('+ New employee') }}</a>
        </div>
    @else
        <form method="POST" action="{{ route('app.payroll.store') }}" class="bg-white rounded-xl border border-slate-100 p-6 space-y-5">
            @csrf
            <div>
                <h3 class="font-semibold text-slate-900 mb-1">{{ __('Run payroll') }}</h3>
                <p class="text-sm text-slate-500">{{ __('Generates a draft payslip for each of your :count active employees, using their current salary and calculated GOSI contributions. Review and adjust before approving.', ['count' => $activeEmployeeCount]) }}</p>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700">{{ __('Month') }}</label>
                    <select name="period_month" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                        @for ($m = 1; $m <= 12; $m++)
                            <option value="{{ $m }}" @selected($m === $nextMonth)>{{ \Carbon\Carbon::create(2000, $m, 1)->translatedFormat('F') }}</option>
                        @endfor
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">{{ __('Year') }}</label>
                    <input type="number" name="period_year" value="{{ old('period_year', $nextYear) }}" min="2000" max="2100" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Pay date') }}</label>
                <input type="date" name="pay_date" value="{{ old('pay_date', now()->toDateString()) }}" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div class="flex gap-3">
                <button type="submit" class="rounded-lg bg-brand-600 px-6 py-2.5 font-semibold text-white hover:bg-brand-700">{{ __('Generate draft') }}</button>
                <a href="{{ route('app.payroll.index') }}" class="rounded-lg border border-slate-200 px-6 py-2.5 font-semibold text-slate-600 hover:border-slate-300">{{ __('Cancel') }}</a>
            </div>
        </form>
    @endif
</div>
@endsection
