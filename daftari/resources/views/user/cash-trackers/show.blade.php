@extends('layouts.app')

@section('title', $tracker->name)

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <a href="{{ route('app.cash-trackers.index') }}" class="text-xs text-slate-400 hover:text-slate-600">&larr; {{ __('Cash Trackers') }}</a>
        <h2 class="text-lg font-semibold text-slate-900 mt-1">{{ $tracker->name }}</h2>
        @if ($tracker->notes)<p class="text-sm text-slate-500 mt-0.5">{{ $tracker->notes }}</p>@endif
    </div>
    <div class="flex items-center gap-2 shrink-0">
        <button type="button" onclick="document.getElementById('add-entry-modal').showModal()" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('+ Add entry') }}</button>
        <a href="{{ route('app.cash-trackers.pdf', $tracker) }}" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download report') }}</a>
        <form method="POST" action="{{ route('app.cash-trackers.destroy', $tracker) }}" onsubmit="return confirm('{{ __('Delete this cash tracker and every entry in it? This cannot be undone.') }}')">
            @csrf @method('DELETE')
            <button type="submit" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-red-600 hover:border-red-300">{{ __('Delete') }}</button>
        </form>
    </div>
</div>

<div class="grid sm:grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-slate-100 p-4">
        <p class="text-xs text-slate-400">{{ __('Total deposited') }}</p>
        <p class="text-lg font-bold text-emerald-600 mt-1">{{ \App\Support\Money::format($tracker->totalDeposited(), $tracker->currency) }}</p>
    </div>
    <div class="bg-white rounded-xl border border-slate-100 p-4">
        <p class="text-xs text-slate-400">{{ __('Total withdrawn / used') }}</p>
        <p class="text-lg font-bold text-red-600 mt-1">{{ \App\Support\Money::format($tracker->totalWithdrawn(), $tracker->currency) }}</p>
    </div>
    <div class="bg-white rounded-xl border border-slate-100 p-4">
        <p class="text-xs text-slate-400">{{ __('Current balance') }}</p>
        <p class="text-lg font-bold text-slate-900 mt-1">{{ \App\Support\Money::format($tracker->balance(), $tracker->currency) }}</p>
    </div>
</div>

<div class="grid lg:grid-cols-3 gap-6 items-start">
    <div class="lg:col-span-2 bg-white rounded-xl border border-slate-100 overflow-hidden">
        <div class="px-5 py-3 border-b border-slate-100">
            <h3 class="text-sm font-semibold text-slate-900">{{ __('Entries') }}</h3>
        </div>
        @if ($tracker->entries->isEmpty())
            <div class="px-6 py-12 text-center text-sm text-slate-400">{{ __('No entries yet — add the deposit(s) first, then every withdrawal/use of the cash.') }}</div>
        @else
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-slate-500 border-b border-slate-100">
                        <th class="px-5 py-2.5 font-medium">{{ __('Date') }}</th>
                        <th class="px-5 py-2.5 font-medium">{{ __('Party') }}</th>
                        <th class="px-5 py-2.5 font-medium">{{ __('Purpose / notes') }}</th>
                        <th class="px-5 py-2.5 font-medium text-right">{{ __('Deposit') }}</th>
                        <th class="px-5 py-2.5 font-medium text-right">{{ __('Withdrawn') }}</th>
                        <th class="px-5 py-2.5 font-medium text-right">{{ __('Balance') }}</th>
                        <th class="px-5 py-2.5"></th>
                    </tr>
                </thead>
                <tbody>
                    @php($running = 0)
                    @foreach ($tracker->entries as $entry)
                        @php($running += $entry->isDeposit() ? $entry->amount : -$entry->amount)
                        <tr class="border-b border-slate-50 last:border-0">
                            <td class="px-5 py-2.5 text-slate-600 whitespace-nowrap">{{ $entry->date->format('Y-m-d') }}</td>
                            <td class="px-5 py-2.5 text-slate-800">{{ $entry->party_name ?: '—' }}</td>
                            <td class="px-5 py-2.5 text-slate-500">
                                {{ $entry->purpose ?: '—' }}
                                @if ($entry->notes)<span class="block text-xs text-slate-400">{{ $entry->notes }}</span>@endif
                            </td>
                            <td class="px-5 py-2.5 text-right tabular-nums text-emerald-600">{{ $entry->isDeposit() ? \App\Support\Money::format($entry->amount, $tracker->currency) : '—' }}</td>
                            <td class="px-5 py-2.5 text-right tabular-nums text-red-600">{{ ! $entry->isDeposit() ? \App\Support\Money::format($entry->amount, $tracker->currency) : '—' }}</td>
                            <td class="px-5 py-2.5 text-right tabular-nums font-semibold text-slate-900">{{ \App\Support\Money::format($running, $tracker->currency) }}</td>
                            <td class="px-5 py-2.5 text-right">
                                <form method="POST" action="{{ route('app.cash-trackers.entries.destroy', [$tracker, $entry]) }}" onsubmit="return confirm('{{ __('Remove this entry?') }}')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-xs text-slate-400 hover:text-red-600">{{ __('Remove') }}</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="bg-white rounded-xl border border-slate-100 p-5">
        <h3 class="text-sm font-semibold text-slate-900 mb-3">{{ __('By party') }}</h3>
        @if ($summary->isEmpty())
            <p class="text-sm text-slate-400">{{ __('Nothing to summarize yet.') }}</p>
        @else
            <div class="space-y-3">
                @foreach ($summary as $row)
                    <div class="border-b border-slate-50 last:border-0 pb-3 last:pb-0">
                        <p class="text-sm font-medium text-slate-800">{{ $row['party'] }}</p>
                        <div class="mt-1 flex items-center justify-between text-xs text-slate-500">
                            <span>{{ __('Deposited') }}: <span class="text-emerald-600 font-medium">{{ \App\Support\Money::format($row['deposited'], $tracker->currency) }}</span></span>
                        </div>
                        <div class="flex items-center justify-between text-xs text-slate-500">
                            <span>{{ __('Withdrawn') }}: <span class="text-red-600 font-medium">{{ \App\Support\Money::format($row['withdrawn'], $tracker->currency) }}</span></span>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>

<dialog id="add-entry-modal" class="rounded-2xl border border-slate-100 p-0 w-full max-w-lg backdrop:bg-slate-900/40">
    <div class="p-6">
        <div class="flex items-start justify-between mb-4">
            <h3 class="text-lg font-bold text-slate-900">{{ __('Add entry') }}</h3>
            <button type="button" onclick="document.getElementById('add-entry-modal').close()" class="text-slate-400 hover:text-slate-600">✕</button>
        </div>
        <form method="POST" action="{{ route('app.cash-trackers.entries.store', $tracker) }}" class="grid sm:grid-cols-2 gap-3">
            @csrf
            <div>
                <label class="block text-xs font-medium text-slate-500">{{ __('Date') }}</label>
                <input type="date" name="date" value="{{ old('date', now()->toDateString()) }}" required class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500">{{ __('Type') }}</label>
                <select name="type" required class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                    <option value="deposit">{{ __('Deposit — cash coming in') }}</option>
                    <option value="withdrawal">{{ __('Withdrawal — cash going out / used') }}</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500">{{ __('Amount') }}</label>
                <input type="number" step="0.01" min="0.01" name="amount" required class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500">{{ __('Party (who gave / received it)') }}</label>
                <input type="text" name="party_name" placeholder="{{ __('e.g. Project Manager, Owner, Myself') }}" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div class="sm:col-span-2">
                <label class="block text-xs font-medium text-slate-500">{{ __('Purpose') }}</label>
                <input type="text" name="purpose" placeholder="{{ __('e.g. Paid to project manager for site expenses') }}" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div class="sm:col-span-2">
                <label class="block text-xs font-medium text-slate-500">{{ __('Notes (optional)') }}</label>
                <textarea name="notes" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500"></textarea>
            </div>
            <div class="sm:col-span-2">
                <button type="submit" class="w-full rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Add entry') }}</button>
            </div>
        </form>
    </div>
</dialog>
@endsection
