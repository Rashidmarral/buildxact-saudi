@extends('layouts.app')

@section('title', __('Settings'))

@section('content')
<form method="POST" action="{{ route('app.settings.update') }}" class="max-w-2xl bg-white rounded-xl border border-slate-100 p-6 space-y-5">
    @csrf
    @method('PUT')

    <div class="grid sm:grid-cols-2 gap-5">
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Company name') }}</label>
            <input type="text" name="name" value="{{ old('name', $company->name) }}" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Company name (Arabic)') }}</label>
            <input type="text" name="name_ar" value="{{ old('name_ar', $company->name_ar) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('VAT number') }}</label>
            <input type="text" name="vat_number" value="{{ old('vat_number', $company->vat_number) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('CR number') }}</label>
            <input type="text" name="cr_number" value="{{ old('cr_number', $company->cr_number) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Email') }}</label>
            <input type="email" name="email" value="{{ old('email', $company->email) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Phone') }}</label>
            <input type="text" name="phone" value="{{ old('phone', $company->phone) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('City') }}</label>
            <input type="text" name="city" value="{{ old('city', $company->city) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Invoice prefix') }}</label>
            <input type="text" name="invoice_prefix" value="{{ old('invoice_prefix', $company->invoice_prefix) }}" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div class="sm:col-span-2">
            <label class="block text-sm font-medium text-slate-700">{{ __('Address') }}</label>
            <input type="text" name="address" value="{{ old('address', $company->address) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
    </div>

    <button type="submit" class="rounded-lg bg-brand-600 px-6 py-2.5 font-semibold text-white hover:bg-brand-700">{{ __('Save settings') }}</button>
</form>

<div class="max-w-2xl mt-6 bg-white rounded-xl border border-slate-100 p-6 flex items-center justify-between">
    <div>
        <h3 class="font-semibold text-slate-900">{{ __('Branches') }}</h3>
        <p class="text-sm text-slate-500">
            @if (auth()->user()->company->default_branch_id)
                {{ __('Default branch:') }} {{ auth()->user()->company->defaultBranch()?->name }}
            @else
                {{ __('No branches yet — your company details are used on new documents.') }}
            @endif
        </p>
    </div>
    <a href="{{ route('app.branches.index') }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Manage branches') }}</a>
</div>
@endsection
