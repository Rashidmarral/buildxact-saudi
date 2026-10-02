@extends('layouts.admin')

@section('title', __('Languages'))

@section('content')
<div class="mb-6 flex flex-wrap items-center justify-between gap-4">
    <div>
        <p class="text-sm text-slate-500">{{ __('Override any text string used across the marketing site, user panel, and admin panel — in English or Arabic — without a deploy.') }}</p>
        <p class="mt-1 text-xs text-slate-400">{{ __(':total strings · :overridden overridden', ['total' => $totalKeys, 'overridden' => $overriddenCount]) }}</p>
    </div>
    <form method="GET" action="{{ route('admin.translations.index') }}" class="flex gap-2">
        <input type="search" name="q" value="{{ $search }}" placeholder="{{ __('Search strings…') }}" class="w-64 rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        <button type="submit" class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">{{ __('Search') }}</button>
        @if ($search !== '')
            <a href="{{ route('admin.translations.index') }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">{{ __('Clear') }}</a>
        @endif
    </form>
</div>

<div class="space-y-3">
    @forelse ($rows as $row)
        <details class="group rounded-xl border border-slate-100 bg-white open:shadow-soft" @if ($row['en_override'] || $row['ar_override']) open @endif>
            <summary class="flex cursor-pointer items-center justify-between gap-3 px-5 py-3.5 text-sm">
                <span class="min-w-0 flex-1 truncate font-medium text-slate-800">{{ $row['en_default'] }}</span>
                @if ($row['en_override'] || $row['ar_override'])
                    <span class="shrink-0 rounded-full bg-brand-50 px-2.5 py-0.5 text-xs font-semibold text-brand-700">{{ __('Overridden') }}</span>
                @endif
            </summary>
            <form method="POST" action="{{ route('admin.translations.update') }}" class="space-y-4 border-t border-slate-100 px-5 py-4">
                @csrf
                <input type="hidden" name="key" value="{{ $row['key'] }}">
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('English') }}</label>
                        <p class="mt-1 text-xs text-slate-400">{{ __('Default') }}: <span class="text-slate-500">{{ $row['en_default'] }}</span></p>
                        <textarea name="en_value" rows="2" placeholder="{{ __('Leave blank to use the default') }}" class="mt-1.5 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">{{ old('en_value', $row['en_override']) }}</textarea>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('Arabic') }}</label>
                        <p class="mt-1 text-xs text-slate-400" dir="rtl">{{ __('Default') }}: <span class="text-slate-500">{{ $row['ar_default'] ?? '—' }}</span></p>
                        <textarea name="ar_value" rows="2" dir="rtl" placeholder="{{ __('Leave blank to use the default') }}" class="mt-1.5 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">{{ old('ar_value', $row['ar_override']) }}</textarea>
                    </div>
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Save') }}</button>
                </div>
            </form>
        </details>
    @empty
        <div class="rounded-xl border border-slate-100 bg-white px-6 py-10 text-center text-sm text-slate-500">
            {{ __('No strings match your search.') }}
        </div>
    @endforelse
</div>

<div class="mt-6">
    {{ $rows->links() }}
</div>
@endsection
