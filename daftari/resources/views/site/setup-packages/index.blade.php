@extends('layouts.site')

@section('title', __('Done-For-You Setup Packages') . ' · Daftari')

@section('content')
<div x-data="{ selected: '' }">
<section class="mx-auto max-w-3xl px-6 pb-6 pt-16 text-center">
    <h1 class="text-3xl font-extrabold text-slate-900 md:text-4xl">{{ __('Done-For-You Setup Packages') }}</h1>
    <p class="mt-4 text-slate-600">{{ __("Prefer to have our team configure things for you? Choose a package below and we'll handle the setup.") }}</p>
</section>

<section class="mx-auto max-w-5xl px-6 pb-16">
    @if ($setupPackages->isEmpty())
        <p class="text-center text-slate-500">{{ __('No setup packages are available right now — please check back soon.') }}</p>
    @else
        <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
            @foreach ($setupPackages as $package)
                <div x-data x-reveal class="flex flex-col rounded-2xl border border-slate-100 bg-white p-6 shadow-card">
                    <h3 class="text-lg font-bold text-slate-900">{{ $package->name() }}</h3>
                    <p class="mt-2 text-xl font-extrabold text-brand-700">{{ $package->priceLabel() }}</p>
                    @if ($package->description())
                        <p class="mt-3 text-sm text-slate-600">{{ $package->description() }}</p>
                    @endif
                    @if (!empty($package->features()))
                        <ul class="mt-4 space-y-2 text-sm text-slate-600">
                            @foreach ($package->features() as $feature)
                                <li class="flex items-start gap-2">
                                    <span class="mt-0.5 inline-flex h-4 w-4 flex-none items-center justify-center rounded-full bg-emerald-100 text-emerald-700">✓</span>
                                    {{ $feature }}
                                </li>
                            @endforeach
                        </ul>
                    @endif
                    <button type="button" @click="selected = '{{ $package->id }}'; $refs.requestForm.scrollIntoView({ behavior: 'smooth' })" class="mt-6 w-full rounded-lg bg-brand-600 px-4 py-2.5 font-semibold text-white hover:bg-brand-700">{{ __('Request this package') }}</button>
                </div>
            @endforeach
        </div>
    @endif
</section>

@if ($setupPackages->isNotEmpty())
<section class="mx-auto max-w-2xl px-6 pb-20" x-ref="requestForm">
    @if (session('status'))
        <div class="mb-6 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 text-sm">
            {{ session('status') }}
        </div>
    @endif
    @if ($errors->any())
        <div class="mb-8 rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">
            <ul class="list-disc ps-4 space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('setup-packages.submit') }}" class="space-y-5 bg-white rounded-2xl border border-slate-100 p-8 shadow-card">
        @csrf
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Package') }}</label>
            <select name="setup_package_id" x-model="selected" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                <option value="">{{ __('Select a package') }}</option>
                @foreach ($setupPackages as $package)
                    <option value="{{ $package->id }}">{{ $package->name() }} — {{ $package->priceLabel() }}</option>
                @endforeach
            </select>
        </div>
        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Name') }}</label>
                <input type="text" name="name" value="{{ old('name') }}" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Email') }}</label>
                <input type="email" name="email" value="{{ old('email') }}" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Phone (optional)') }}</label>
                <input type="text" name="phone" value="{{ old('phone') }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Company name (optional)') }}</label>
                <input type="text" name="company_name" value="{{ old('company_name') }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Anything we should know? (optional)') }}</label>
            <textarea name="message" rows="3" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">{{ old('message') }}</textarea>
        </div>
        <button type="submit" class="btn-shine w-full rounded-lg bg-brand-600 px-6 py-3 font-semibold text-white hover:bg-brand-700">{{ __('Submit request') }}</button>
    </form>
</section>
@endif
</div>
@endsection
