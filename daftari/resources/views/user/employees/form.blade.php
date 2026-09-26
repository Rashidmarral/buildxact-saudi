@extends('layouts.app')

@section('title', $employee->exists ? __('Edit Employee') : __('New Employee'))

@section('content')
<form method="POST" action="{{ $employee->exists ? route('app.employees.update', $employee) : route('app.employees.store') }}" class="max-w-3xl space-y-6">
    @csrf
    @if ($employee->exists) @method('PUT') @endif

    <div class="bg-white rounded-xl border border-slate-100 p-6 space-y-5">
        <h3 class="font-semibold text-slate-900">{{ __('Personal details') }}</h3>
        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Full name') }}</label>
                <input type="text" name="full_name" value="{{ old('full_name', $employee->full_name) }}" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Full name (Arabic)') }}</label>
                <input type="text" name="full_name_ar" dir="rtl" value="{{ old('full_name_ar', $employee->full_name_ar) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('National ID / Iqama number') }}</label>
                <input type="text" name="national_id" value="{{ old('national_id', $employee->national_id) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Nationality') }}</label>
                <input type="text" name="nationality" value="{{ old('nationality', $employee->nationality) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Date of birth') }}</label>
                <input type="date" name="date_of_birth" value="{{ old('date_of_birth', optional($employee->date_of_birth)->toDateString()) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Gender') }}</label>
                <select name="gender" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                    <option value="">—</option>
                    <option value="male" @selected(old('gender', $employee->gender) === 'male')>{{ __('Male') }}</option>
                    <option value="female" @selected(old('gender', $employee->gender) === 'female')>{{ __('Female') }}</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Mobile') }}</label>
                <input type="text" name="mobile" value="{{ old('mobile', $employee->mobile) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Email') }}</label>
                <input type="email" name="email" value="{{ old('email', $employee->email) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Address') }}</label>
            <input type="text" name="address" value="{{ old('address', $employee->address) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <label class="flex items-center gap-2 text-sm text-slate-600">
            <input type="checkbox" name="is_saudi" value="1" @checked(old('is_saudi', $employee->is_saudi ?? true)) class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
            {{ __('Saudi national') }}
        </label>
        <p class="text-xs text-slate-400 -mt-3">{{ __('Determines which GOSI contribution branches apply — Saudi employees are covered by Annuities and SANED as well as Occupational Hazards; non-Saudi employees by Occupational Hazards only.') }}</p>
    </div>

    <div class="bg-white rounded-xl border border-slate-100 p-6 space-y-5">
        <h3 class="font-semibold text-slate-900">{{ __('Employment') }}</h3>
        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Branch') }}</label>
                <select name="branch_id" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                    <option value="">—</option>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}" @selected(old('branch_id', $employee->branch_id) == $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Job title') }}</label>
                <input type="text" name="job_title" value="{{ old('job_title', $employee->job_title) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Department') }}</label>
                <input type="text" name="department" value="{{ old('department', $employee->department) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Hire date') }}</label>
                <input type="date" name="hire_date" value="{{ old('hire_date', optional($employee->hire_date)->toDateString()) }}" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-100 p-6 space-y-5">
        <h3 class="font-semibold text-slate-900">{{ __('Bank details') }}</h3>
        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Bank name') }}</label>
                <input type="text" name="bank_name" value="{{ old('bank_name', $employee->bank_name) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('IBAN') }}</label>
                <input type="text" name="iban" value="{{ old('iban', $employee->iban) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('GOSI subscription number') }}</label>
                <input type="text" name="gosi_subscription_number" value="{{ old('gosi_subscription_number', $employee->gosi_subscription_number) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-100 p-6 space-y-5">
        <h3 class="font-semibold text-slate-900">{{ __('Salary') }}</h3>
        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Basic salary') }}</label>
                <input type="number" step="0.01" min="0" name="basic_salary" value="{{ old('basic_salary', $employee->basic_salary) }}" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Housing allowance') }}</label>
                <input type="number" step="0.01" min="0" name="housing_allowance" value="{{ old('housing_allowance', $employee->housing_allowance) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Transport allowance') }}</label>
                <input type="number" step="0.01" min="0" name="transport_allowance" value="{{ old('transport_allowance', $employee->transport_allowance) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Other allowance') }}</label>
                <input type="number" step="0.01" min="0" name="other_allowance" value="{{ old('other_allowance', $employee->other_allowance) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
        </div>
        <p class="text-xs text-slate-400">{{ __('Basic salary and housing allowance together form the GOSI contributory wage.') }}</p>
    </div>

    <div class="bg-white rounded-xl border border-slate-100 p-6">
        <label class="block text-sm font-medium text-slate-700">{{ __('Notes') }}</label>
        <textarea name="notes" rows="3" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">{{ old('notes', $employee->notes) }}</textarea>
    </div>

    <div class="flex gap-3">
        <button type="submit" class="rounded-lg bg-brand-600 px-6 py-2.5 font-semibold text-white hover:bg-brand-700">{{ __('Save') }}</button>
        <a href="{{ route('app.employees.index') }}" class="rounded-lg border border-slate-200 px-6 py-2.5 font-semibold text-slate-600 hover:border-slate-300">{{ __('Cancel') }}</a>
    </div>
</form>
@endsection
