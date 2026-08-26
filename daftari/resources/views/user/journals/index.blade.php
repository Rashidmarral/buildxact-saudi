@extends('layouts.app')

@section('title', __('Journals'))

@section('content')
@include('user.accounting.partials.tabs')

<div class="mb-6">
    <h2 class="text-lg font-semibold text-slate-900">{{ __('Journal Entries') }}</h2>
    <p class="text-sm text-slate-500 mt-1">{{ __('Every posting made by invoices, bills, payments, and adjustments.') }}</p>
</div>

<form method="GET" class="bg-white rounded-xl border border-slate-100 p-4 mb-6 flex flex-wrap items-center gap-3">
    <select name="source_type" class="rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        <option value="">{{ __('All sources') }}</option>
        @foreach ($sourceTypes as $type)
            <option value="{{ $type }}" @selected(request('source_type') === $type)>{{ ucfirst(str_replace('_', ' ', $type)) }}</option>
        @endforeach
    </select>
    <input type="date" name="from" value="{{ request('from') }}" class="rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
    <span class="text-slate-400 text-sm">{{ __('to') }}</span>
    <input type="date" name="to" value="{{ request('to') }}" class="rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
    <button type="submit" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Filter') }}</button>
</form>

<div class="bg-white rounded-xl border border-slate-100">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-slate-500 border-b border-slate-100">
                <th class="px-5 py-3 font-medium">{{ __('Entry') }}</th>
                <th class="px-5 py-3 font-medium">{{ __('Date') }}</th>
                <th class="px-5 py-3 font-medium">{{ __('Description') }}</th>
                <th class="px-5 py-3 font-medium">{{ __('Source') }}</th>
                <th class="px-5 py-3 font-medium text-end">{{ __('Amount') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($entries as $entry)
                <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50">
                    <td class="px-5 py-3"><a href="{{ route('app.journals.show', $entry) }}" class="font-semibold text-brand-700 hover:underline">{{ $entry->entry_number }}</a></td>
                    <td class="px-5 py-3 text-slate-500">{{ $entry->entry_date->format('Y-m-d') }}</td>
                    <td class="px-5 py-3">{{ $entry->description }}</td>
                    <td class="px-5 py-3 text-slate-500">{{ ucfirst(str_replace('_', ' ', $entry->source_type ?? '—')) }}</td>
                    <td class="px-5 py-3 text-end">{{ \App\Support\Money::format($entry->total_debit) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-5 py-10 text-center text-slate-400">{{ __('No journal entries yet — they appear automatically as invoices, bills, and payments are recorded.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $entries->links() }}</div>
@endsection
