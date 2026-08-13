@extends('layouts.app')

@section('title', $client->exists ? __('Edit Client') : __('New Client'))

@section('content')
<form method="POST" action="{{ $client->exists ? route('app.clients.update', $client) : route('app.clients.store') }}" class="max-w-2xl bg-white rounded-xl border border-slate-100 p-6 space-y-5">
    @csrf
    @if ($client->exists) @method('PUT') @endif

    <div class="grid sm:grid-cols-2 gap-5">
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Name') }}</label>
            <input type="text" name="name" value="{{ old('name', $client->name) }}" required class="mt-1 w-full rounded-lg border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Name (Arabic)') }}</label>
            <input type="text" name="name_ar" value="{{ old('name_ar', $client->name_ar) }}" class="mt-1 w-full rounded-lg border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('VAT number') }}</label>
            <input type="text" name="vat_number" value="{{ old('vat_number', $client->vat_number) }}" class="mt-1 w-full rounded-lg border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('CR number') }}</label>
            <input type="text" name="cr_number" value="{{ old('cr_number', $client->cr_number) }}" class="mt-1 w-full rounded-lg border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Email') }}</label>
            <input type="email" name="email" value="{{ old('email', $client->email) }}" class="mt-1 w-full rounded-lg border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Phone') }}</label>
            <input type="text" name="phone" value="{{ old('phone', $client->phone) }}" class="mt-1 w-full rounded-lg border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('City') }}</label>
            <input type="text" name="city" value="{{ old('city', $client->city) }}" class="mt-1 w-full rounded-lg border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Address') }}</label>
            <input type="text" name="address" value="{{ old('address', $client->address) }}" class="mt-1 w-full rounded-lg border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
    </div>

    <div class="flex gap-3 pt-2">
        <button type="submit" class="rounded-lg bg-brand-600 px-6 py-2.5 font-semibold text-white hover:bg-brand-700">{{ __('Save') }}</button>
        <a href="{{ route('app.clients.index') }}" class="rounded-lg border border-slate-200 px-6 py-2.5 font-semibold text-slate-600 hover:border-slate-300">{{ __('Cancel') }}</a>
    </div>
</form>
@endsection
