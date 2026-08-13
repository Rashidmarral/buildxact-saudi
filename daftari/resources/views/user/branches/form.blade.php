@extends('layouts.app')

@section('title', $branch->exists ? __('Edit Branch') : __('New Branch'))

@section('content')
<form method="POST" action="{{ $branch->exists ? route('app.branches.update', $branch) : route('app.branches.store') }}" class="max-w-2xl space-y-6">
    @csrf
    @if ($branch->exists) @method('PUT') @endif

    <div class="bg-white rounded-xl border border-slate-100 p-6 space-y-5">
        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Branch name') }}</label>
                <input type="text" name="name" value="{{ old('name', $branch->name) }}" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Branch name (Arabic)') }}</label>
                <input type="text" name="name_ar" value="{{ old('name_ar', $branch->name_ar) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('CR number') }}</label>
            <input type="text" name="cr_number" value="{{ old('cr_number', $branch->cr_number) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>

        <h3 class="font-semibold text-slate-900 pt-2">{{ __('Address') }}</h3>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Street name') }}</label>
            <input type="text" name="street_name" value="{{ old('street_name', $branch->street_name) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Building number') }}</label>
                <input type="text" name="building_number" value="{{ old('building_number', $branch->building_number) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('District') }}</label>
                <input type="text" name="district" value="{{ old('district', $branch->district) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
        </div>
        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('City') }}</label>
                <input type="text" name="city" value="{{ old('city', $branch->city) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Postal code') }}</label>
                <input type="text" name="postal_code" value="{{ old('postal_code', $branch->postal_code) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
        </div>
        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('State') }}</label>
                <input type="text" name="state" value="{{ old('state', $branch->state) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Country') }}</label>
                <input type="text" name="country" value="{{ old('country', $branch->country) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
        </div>

        <h3 class="font-semibold text-slate-900 pt-2">{{ __('Contact') }}</h3>
        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Phone') }}</label>
                <input type="text" name="phone" value="{{ old('phone', $branch->phone) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Email') }}</label>
                <input type="email" name="email" value="{{ old('email', $branch->email) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
        </div>
    </div>

    <div class="flex gap-3">
        <button type="submit" class="rounded-lg bg-brand-600 px-6 py-2.5 font-semibold text-white hover:bg-brand-700">{{ __('Save') }}</button>
        <a href="{{ route('app.branches.index') }}" class="rounded-lg border border-slate-200 px-6 py-2.5 font-semibold text-slate-600 hover:border-slate-300">{{ __('Cancel') }}</a>
    </div>
</form>
@endsection
