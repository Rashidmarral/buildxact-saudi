@extends('layouts.app')

@section('title', __('Add Operator'))

@section('content')
<form method="POST" action="{{ route('app.machinery.operators.store') }}" class="max-w-2xl space-y-6">
    @csrf

    <div class="bg-white rounded-xl border border-slate-100 p-6 space-y-5">
        <h3 class="font-semibold text-slate-900">{{ __('Operator details') }}</h3>
        <p class="text-xs text-slate-400 -mt-3">{{ __('A quick way to add a driver/operator so they can be selected on machinery assets and rental contracts. Salary, GOSI, and bank details can be added later from Employees.') }}</p>
        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Full name') }}</label>
                <input type="text" name="full_name" value="{{ old('full_name') }}" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Full name (Arabic)') }}</label>
                <input type="text" name="full_name_ar" dir="rtl" value="{{ old('full_name_ar') }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('National ID / Iqama number') }}</label>
                <input type="text" name="national_id" value="{{ old('national_id') }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Mobile') }}</label>
                <input type="text" name="mobile" value="{{ old('mobile') }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('License number') }}</label>
                <input type="text" name="license_number" value="{{ old('license_number') }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('License expiry date') }}</label>
                <input type="date" name="license_expiry_date" value="{{ old('license_expiry_date') }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Hire date') }}</label>
                <input type="date" name="hire_date" value="{{ old('hire_date', now()->toDateString()) }}" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
        </div>
    </div>

    <div class="flex justify-end gap-3">
        <a href="{{ route('app.machinery.operators.index') }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600">{{ __('Cancel') }}</a>
        <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Add operator') }}</button>
    </div>
</form>
@endsection
