<?php // Recurring journal entries: same pause/resume/edit/delete list pattern as Recurring Invoices. ?>
@extends('layouts.app')

@section('title', __('Recurring Journal Entries'))

@section('content')
<div class="flex items-center justify-between mb-6">
    <p class="text-sm text-slate-500">{{ __('Automatically post a journal entry on a schedule — for monthly depreciation, accruals, and other recurring adjustments.') }}</p>
    <a href="{{ route('app.recurring-journal-entries.create') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('+ New recurring entry') }}</a>
</div>

<div class="bg-white rounded-xl border border-slate-100">
    @if ($recurringEntries->isEmpty())
        <p class="px-6 py-8 text-sm text-slate-500">{{ __('No recurring journal entries yet.') }}</p>
    @else
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="px-6 py-3 font-medium">{{ __('Title') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Frequency') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Next posting') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Posted') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Status') }}</th>
                    <th class="px-6 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($recurringEntries as $recurringEntry)
                    <tr class="border-b border-slate-50 last:border-0">
                        <td class="px-6 py-3 font-medium text-slate-800">{{ $recurringEntry->title }}</td>
                        <td class="px-6 py-3">{{ __(ucfirst($recurringEntry->frequency)) }}</td>
                        <td class="px-6 py-3">{{ $recurringEntry->status === 'active' ? $recurringEntry->next_run_date->format('Y-m-d') : '—' }}</td>
                        <td class="px-6 py-3">{{ $recurringEntry->generated_count }}</td>
                        <td class="px-6 py-3">
                            @php($colors = ['active' => 'bg-emerald-50 text-emerald-700', 'paused' => 'bg-amber-50 text-amber-700', 'completed' => 'bg-slate-100 text-slate-600'])
                            <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $colors[$recurringEntry->status] ?? 'bg-slate-100 text-slate-600' }}">
                                {{ __(ucfirst($recurringEntry->status)) }}
                            </span>
                        </td>
                        <td class="px-6 py-3 text-end">
                            <div class="flex items-center justify-end gap-3">
                                @if ($recurringEntry->status === 'active')
                                    <form method="POST" action="{{ route('app.recurring-journal-entries.pause', $recurringEntry) }}">
                                        @csrf
                                        <button type="submit" class="text-slate-500 hover:text-slate-700">{{ __('Pause') }}</button>
                                    </form>
                                @elseif ($recurringEntry->status === 'paused')
                                    <form method="POST" action="{{ route('app.recurring-journal-entries.resume', $recurringEntry) }}">
                                        @csrf
                                        <button type="submit" class="text-brand-700 hover:underline">{{ __('Resume') }}</button>
                                    </form>
                                @endif
                                <a href="{{ route('app.recurring-journal-entries.edit', $recurringEntry) }}" class="text-brand-700 hover:underline">{{ __('Edit') }}</a>
                                <form method="POST" action="{{ route('app.recurring-journal-entries.destroy', $recurringEntry) }}" onsubmit="return confirm('{{ __('Delete this recurring journal entry? This does not affect entries already posted.') }}')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:underline">{{ __('Delete') }}</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

<div class="mt-4">{{ $recurringEntries->links() }}</div>
@endsection
