@extends('layouts.app')

@section('title', __(':branch — Account Overrides', ['branch' => $branch->name]))

@section('content')
@include('user.accounting.partials.tabs')

<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <div>
        <h2 class="text-lg font-semibold text-slate-900">{{ __(':branch — Account Overrides', ['branch' => $branch->name]) }}</h2>
        <p class="text-sm text-slate-500 mt-1">{{ __('Sales and purchases posted under this branch use these accounts instead of the company default, for any key set below.') }}</p>
    </div>
    <a href="{{ route('app.accounts.index') }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Back to Chart of Accounts') }}</a>
</div>

<div class="bg-white rounded-xl border border-slate-100 p-6">
    <div class="divide-y divide-slate-50">
        @foreach ($overridableKeys as $key => $meta)
            @php($override = $overrides[$key] ?? null)
            @php($companyDefault = $companyMappings[$key]->account ?? null)
            @php($compatibleAccounts = $meta['allowed_type'] ? $activeAccounts->where('type', $meta['allowed_type']) : $activeAccounts)
            <form method="POST" action="{{ route('app.accounts.branch-mappings.update', $branch) }}" class="flex items-center justify-between gap-4 py-3">
                @csrf
                <input type="hidden" name="key" value="{{ $key }}">
                <div>
                    <p class="text-sm font-semibold text-slate-800">{{ __($meta['label']) }}</p>
                    <p class="text-xs text-slate-400">{{ __('Company default') }}: {{ $companyDefault ? $companyDefault->code.' - '.$companyDefault->name : __('not set') }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <select name="account_id" class="rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                        <option value="">{{ __('Use company default') }}</option>
                        @foreach ($compatibleAccounts as $acc)
                            <option value="{{ $acc->id }}" @selected(($override->account_id ?? null) === $acc->id)>{{ $acc->code }} - {{ $acc->name }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:border-slate-300">{{ __('Save') }}</button>
                </div>
            </form>
        @endforeach
    </div>
</div>
@endsection
