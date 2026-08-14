@extends('layouts.app')

@section('title', $journalEntry->entry_number)

@section('content')
@include('user.accounting.partials.tabs')

<div class="flex items-center justify-between mb-6">
    <div>
        <h2 class="text-lg font-semibold text-slate-900">{{ $journalEntry->entry_number }}</h2>
        <p class="text-sm text-slate-500 mt-1">{{ $journalEntry->description }}</p>
    </div>
    <a href="{{ route('app.journals.index') }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Back to journals') }}</a>
</div>

<div class="bg-white rounded-xl border border-slate-100 p-6 mb-6 grid sm:grid-cols-3 gap-4 text-sm">
    <div><p class="text-xs text-slate-400">{{ __('Date') }}</p><p class="font-semibold text-slate-800">{{ $journalEntry->entry_date->format('Y-m-d') }}</p></div>
    <div><p class="text-xs text-slate-400">{{ __('Source') }}</p><p class="font-semibold text-slate-800">{{ ucfirst(str_replace('_', ' ', $journalEntry->source_type ?? '—')) }} @if($journalEntry->source_id) #{{ $journalEntry->source_id }} @endif</p></div>
    <div><p class="text-xs text-slate-400">{{ __('Recorded by') }}</p><p class="font-semibold text-slate-800">{{ $journalEntry->creator->name ?? __('System') }}</p></div>
</div>

<div class="bg-white rounded-xl border border-slate-100">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-slate-500 border-b border-slate-100">
                <th class="px-5 py-3 font-medium">{{ __('Account') }}</th>
                <th class="px-5 py-3 font-medium">{{ __('Memo') }}</th>
                <th class="px-5 py-3 font-medium text-end">{{ __('Debit') }}</th>
                <th class="px-5 py-3 font-medium text-end">{{ __('Credit') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($journalEntry->lines as $line)
                <tr class="border-b border-slate-50 last:border-0">
                    <td class="px-5 py-3 font-medium text-slate-800">{{ $line->account->label() }}</td>
                    <td class="px-5 py-3 text-slate-500">{{ $line->memo ?: ($line->costCenter->name ?? '—') }}</td>
                    <td class="px-5 py-3 text-end">{{ $line->debit > 0 ? number_format($line->debit, 2) : '' }}</td>
                    <td class="px-5 py-3 text-end">{{ $line->credit > 0 ? number_format($line->credit, 2) : '' }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="border-t border-slate-200 font-semibold text-slate-900">
                <td class="px-5 py-3" colspan="2">{{ __('Total') }}</td>
                <td class="px-5 py-3 text-end">{{ number_format($journalEntry->totalDebit(), 2) }}</td>
                <td class="px-5 py-3 text-end">{{ number_format($journalEntry->totalCredit(), 2) }}</td>
            </tr>
        </tfoot>
    </table>
</div>
@endsection
