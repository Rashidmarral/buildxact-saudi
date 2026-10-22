@extends('layouts.app')

@section('title', __('Cash Trackers'))

@section('content')
@include('user.bank-accounts.partials.tabs')

<div class="flex items-center justify-between mb-6">
    <p class="text-sm text-slate-500 max-w-2xl">{{ __('A separate, informal cash log — for money that moved outside your normal books (a direct owner handout, an inter-company transfer you managed by hand) but still needs a clear "where did it go" report. Nothing logged here affects your accounts, vouchers, or financial statements.') }}</p>
    <button type="button" onclick="document.getElementById('new-tracker-modal').showModal()" class="shrink-0 rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('+ New cash tracker') }}</button>
</div>

<div class="bg-white rounded-xl border border-slate-100 overflow-hidden">
    @if ($trackers->isEmpty())
        <div class="px-6 py-16 text-center">
            <p class="text-sm text-slate-500 mb-4">{{ __('No cash trackers yet.') }}</p>
            <button type="button" onclick="document.getElementById('new-tracker-modal').showModal()" class="inline-block rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('+ New cash tracker') }}</button>
        </div>
    @else
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="px-6 py-3 font-medium">{{ __('Name') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Entries') }}</th>
                    <th class="px-6 py-3 font-medium text-right">{{ __('Current balance') }}</th>
                    <th class="px-6 py-3 font-medium"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($trackers as $tracker)
                    <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50">
                        <td class="px-6 py-3 font-medium text-slate-900">
                            <a href="{{ route('app.cash-trackers.show', $tracker) }}" class="hover:underline">{{ $tracker->name }}</a>
                            @if ($tracker->notes)<span class="block text-xs font-normal text-slate-400">{{ \Illuminate\Support\Str::limit($tracker->notes, 80) }}</span>@endif
                        </td>
                        <td class="px-6 py-3 text-slate-500">{{ $tracker->entries_count }}</td>
                        <td class="px-6 py-3 text-right font-semibold tabular-nums">{{ \App\Support\Money::format($tracker->balance(), $tracker->currency) }}</td>
                        <td class="px-6 py-3 text-right whitespace-nowrap">
                            <a href="{{ route('app.cash-trackers.show', $tracker) }}" class="text-brand-700 hover:underline">{{ __('Open') }}</a>
                            <span class="text-slate-300 mx-1">&middot;</span>
                            <a href="{{ route('app.cash-trackers.pdf', $tracker) }}" class="text-slate-500 hover:underline">{{ __('Report') }}</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

<dialog id="new-tracker-modal" class="rounded-2xl border border-slate-100 p-0 w-full max-w-md backdrop:bg-slate-900/40">
    <form method="POST" action="{{ route('app.cash-trackers.store') }}" class="p-6 space-y-4">
        @csrf
        <div class="flex items-start justify-between">
            <h3 class="text-lg font-bold text-slate-900">{{ __('New cash tracker') }}</h3>
            <button type="button" onclick="document.getElementById('new-tracker-modal').close()" class="text-slate-400 hover:text-slate-600">✕</button>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500">{{ __('Name') }}</label>
            <input type="text" name="name" required placeholder="{{ __('e.g. Owner transfer — Site A') }}" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500">{{ __('Notes (optional)') }}</label>
            <textarea name="notes" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500"></textarea>
        </div>
        <button type="submit" class="w-full rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Create') }}</button>
    </form>
</dialog>
@endsection
