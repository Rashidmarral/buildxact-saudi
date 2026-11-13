@extends('layouts.admin')

@section('title', __('Data reset — :name', ['name' => $company->name]))

@section('content')
@php
    $totalSelectableCount = collect($grouped)->flatMap(fn ($cats) => array_keys($cats))->count();
@endphp

<div class="flex items-start justify-between mb-6">
    <div>
        <h2 class="text-lg font-semibold text-slate-900">{{ __('Company data reset — :name', ['name' => $company->name]) }}</h2>
        <p class="text-sm text-slate-500 mt-1">{{ __('Permanently deletes the selected transaction categories for this company and restarts their document numbering at 1. Invoices are never offered here and are never touched.') }}</p>
    </div>
    <a href="{{ route('admin.companies.show', $company) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Back') }}</a>
</div>

@if (session('status'))
    <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
@endif
@if ($errors->any())
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
@endif

<div class="mb-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 space-y-1">
    <p>{{ __('Two hard limits worth reading before using this:') }}</p>
    <ul class="list-disc ps-5 space-y-1">
        <li>{{ __('Only the document NUMBER shown to users (BILL-00001, PV-00001, ...) restarts at 1. The underlying database row id cannot be reset — it is shared with every other company\'s rows in the same table.') }}</li>
        <li>{{ __('There is no undo. Take a database backup first if there is any doubt.') }}</li>
    </ul>
</div>

@if ($hasZatcaActivity)
    <div class="rounded-lg bg-slate-50 border border-slate-200 px-4 py-3 text-sm text-slate-600 flex items-start gap-2">
        @include('partials.icon', ['name' => 'shield', 'class' => 'h-5 w-5 text-slate-400 shrink-0 mt-0.5'])
        <span>{{ __('This company has ZATCA activity on file (onboarding, a certificate, or a submission log — even a simulation one). Credit notes, debit notes, and other ZATCA-regulated history are permanently locked, so data reset is disabled entirely for this company.') }}</span>
    </div>
@else
    <div x-data="{
        selected: {},
        confirmName: '',
        get selectedCount() { return Object.values(this.selected).filter(Boolean).length },
        toggleAll(value) {
            @foreach ($grouped as $group => $categories)
                @foreach ($categories as $key => $category)
                    this.selected['{{ $key }}'] = value && {{ $counts[$key] }} > 0;
                @endforeach
            @endforeach
        },
    }" class="bg-white rounded-xl border border-slate-100 p-6">
        <form method="POST" action="{{ route('admin.companies.data-reset.execute', $company) }}" @submit="if (selectedCount === 0 || confirmName !== @js($company->name)) $event.preventDefault()">
            @csrf

            <div class="flex items-center justify-between mb-4">
                <h3 class="font-semibold text-slate-900">{{ __('Select what to wipe') }}</h3>
                <div class="flex items-center gap-3 text-sm">
                    <button type="button" class="text-brand-700 hover:underline" @click="toggleAll(true)">{{ __('Select all') }}</button>
                    <button type="button" class="text-slate-500 hover:underline" @click="toggleAll(false)">{{ __('Clear') }}</button>
                </div>
            </div>

            <div class="space-y-6">
                @foreach ($grouped as $group => $categories)
                    <div>
                        <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-400 mb-2">{{ $group }}</h4>
                        <div class="grid sm:grid-cols-2 gap-x-6 gap-y-2">
                            @foreach ($categories as $key => $category)
                                <label class="flex items-center justify-between gap-3 rounded-lg border border-slate-100 px-3 py-2 {{ $counts[$key] === 0 ? 'opacity-50' : 'hover:border-slate-200' }}">
                                    <span class="flex items-center gap-2">
                                        <input
                                            type="checkbox"
                                            name="categories[]"
                                            value="{{ $key }}"
                                            x-model="selected['{{ $key }}']"
                                            {{ $counts[$key] === 0 ? 'disabled' : '' }}
                                        >
                                        <span class="text-sm text-slate-700">{{ $category['label'] }}</span>
                                    </span>
                                    <span class="text-xs font-semibold tabular-nums {{ $counts[$key] > 0 ? 'text-slate-500' : 'text-slate-300' }}">{{ $counts[$key] }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-6 pt-6 border-t border-slate-100">
                <label class="block text-sm font-medium text-slate-700 mb-1">
                    {{ __('Type the company name to confirm: :name', ['name' => $company->name]) }}
                </label>
                <input
                    type="text"
                    name="confirm_name"
                    x-model="confirmName"
                    class="w-full max-w-md rounded-lg border border-slate-200 text-sm focus:border-red-500 focus:ring-red-500"
                    autocomplete="off"
                >
                @error('confirm_name')
                    <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                @enderror

                <button
                    type="submit"
                    class="mt-4 inline-flex rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 disabled:opacity-40 disabled:cursor-not-allowed"
                    :disabled="selectedCount === 0 || confirmName !== @js($company->name)"
                >
                    <span x-text="selectedCount > 0 ? '{{ __('Permanently wipe') }} ' + selectedCount + ' {{ __('selected categories') }}' : '{{ __('Select at least one category') }}'"></span>
                </button>
            </div>
        </form>
    </div>
@endif
@endsection
