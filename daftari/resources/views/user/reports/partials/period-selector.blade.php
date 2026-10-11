@php($extra = $extra ?? [])
<div class="bg-white rounded-xl border border-slate-100 p-4 mb-6">
    <p class="text-xs font-semibold uppercase text-slate-400 mb-2">{{ __('Period') }} {{ \App\Support\PlatformFormat::date($period['from']) }} &rarr; {{ \App\Support\PlatformFormat::date($period['to']) }}</p>
    <form method="GET" class="flex flex-wrap items-center gap-3">
        @foreach ($extra as $name => $value)
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
        @endforeach
        <select name="period" onchange="document.getElementById('custom-range').classList.toggle('hidden', this.value !== 'custom'); document.getElementById('quarter-picker').classList.toggle('hidden', this.value !== 'quarter'); if (this.value !== 'custom') this.form.submit();" class="rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
            <option value="this_month" @selected($period['preset'] === 'this_month')>{{ __('This month') }}</option>
            <option value="last_month" @selected($period['preset'] === 'last_month')>{{ __('Last month') }}</option>
            <option value="this_quarter" @selected($period['preset'] === 'this_quarter')>{{ __('This quarter') }}</option>
            <option value="last_quarter" @selected($period['preset'] === 'last_quarter')>{{ __('Last quarter') }}</option>
            <option value="quarter" @selected($period['preset'] === 'quarter')>{{ __('Pick a quarter…') }}</option>
            <option value="this_year" @selected($period['preset'] === 'this_year')>{{ __('Current year') }}</option>
            <option value="last_year" @selected($period['preset'] === 'last_year')>{{ __('Last year') }}</option>
            <option value="custom" @selected($period['preset'] === 'custom')>{{ __('Custom') }}</option>
        </select>
        <span id="quarter-picker" class="flex items-center gap-2 {{ $period['preset'] === 'quarter' ? '' : 'hidden' }}">
            <select name="quarter" onchange="this.form.submit()" class="rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                @foreach ([1, 2, 3, 4] as $q)
                    <option value="{{ $q }}" @selected(($period['quarter'] ?? now()->quarter) === $q)>{{ 'Q'.$q }}</option>
                @endforeach
            </select>
            <select name="year" onchange="this.form.submit()" class="rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                @foreach ([now()->year - 1, now()->year, now()->year + 1] as $y)
                    <option value="{{ $y }}" @selected(($period['year'] ?? now()->year) === $y)>{{ $y }}</option>
                @endforeach
            </select>
        </span>
        <span id="custom-range" class="flex items-center gap-2 {{ $period['preset'] === 'custom' ? '' : 'hidden' }}">
            <input type="date" name="from" value="{{ $period['from']->toDateString() }}" class="rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
            <span class="text-slate-400 text-sm">{{ __('to') }}</span>
            <input type="date" name="to" value="{{ $period['to']->toDateString() }}" class="rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
            <button type="submit" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:border-slate-300">{{ __('Apply') }}</button>
        </span>
    </form>
</div>
