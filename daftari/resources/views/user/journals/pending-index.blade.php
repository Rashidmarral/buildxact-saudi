@extends('layouts.app')

@section('title', __('Pending Journal Entries'))

@section('content')
@include('user.accounting.partials.tabs')

<div class="flex items-center justify-between mb-6">
    <div>
        <h2 class="text-lg font-semibold text-slate-900">{{ __('Pending Journal Entries') }}</h2>
        <p class="text-sm text-slate-500 mt-1">{{ __('Manual journal entries at or above your approval threshold wait here — nothing is posted to the ledger until approved.') }}</p>
    </div>
    <a href="{{ route('app.journals.index') }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Back to journals') }}</a>
</div>

@if (session('status'))
    <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
@endif

@if ($errors->any())
    <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
        <ul class="list-disc ps-4">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@if ($pending->isEmpty())
    <div class="bg-white rounded-xl border border-dashed border-slate-200 p-10 text-center">
        <p class="text-sm text-slate-400">{{ __('Nothing waiting for approval right now.') }}</p>
    </div>
@else
    <div class="space-y-4">
        @foreach ($pending as $entry)
            <div class="bg-white rounded-xl border border-slate-100 p-5">
                <div class="flex items-start justify-between mb-3">
                    <div>
                        <p class="font-semibold text-slate-900">{{ $entry->description }}</p>
                        <p class="text-xs text-slate-400 mt-0.5">
                            {{ __('Submitted by :name on :date', ['name' => $entry->creator->name ?? __('Unknown'), 'date' => $entry->created_at->format('Y-m-d H:i')]) }}
                            &middot; {{ __('Entry date') }}: {{ $entry->entry_date->format('Y-m-d') }}
                        </p>
                    </div>
                    <p class="text-lg font-bold text-slate-900">{{ \App\Support\Money::format($entry->total) }}</p>
                </div>

                <table class="w-full text-sm mb-4">
                    <thead>
                        <tr class="text-left text-slate-500 border-b border-slate-100">
                            <th class="py-1.5">{{ __('Account') }}</th>
                            <th class="py-1.5">{{ __('Memo') }}</th>
                            <th class="py-1.5 text-end">{{ __('Debit') }}</th>
                            <th class="py-1.5 text-end">{{ __('Credit') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($entry->lines as $line)
                            <tr class="border-b border-slate-50 last:border-0">
                                <td class="py-1.5">{{ \App\Models\Account::find($line['account_id'])?->name ?? __('Unknown account') }}</td>
                                <td class="py-1.5 text-slate-500">{{ $line['memo'] ?? '—' }}</td>
                                <td class="py-1.5 text-end">{{ (float) ($line['debit'] ?? 0) > 0 ? \App\Support\Money::format($line['debit']) : '—' }}</td>
                                <td class="py-1.5 text-end">{{ (float) ($line['credit'] ?? 0) > 0 ? \App\Support\Money::format($line['credit']) : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <div class="flex items-center gap-3">
                    <form method="POST" action="{{ route('app.journals.pending.approve', $entry) }}">
                        @csrf
                        <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Approve & post') }}</button>
                    </form>
                    <form method="POST" action="{{ route('app.journals.pending.reject', $entry) }}" class="flex items-center gap-2" onsubmit="return confirm('{{ __('Reject this journal entry?') }}')">
                        @csrf
                        <input type="text" name="rejection_reason" placeholder="{{ __('Reason (optional)') }}" class="rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                        <button type="submit" class="rounded-lg border border-red-200 px-4 py-2 text-sm font-semibold text-red-600 hover:border-red-300">{{ __('Reject') }}</button>
                    </form>
                </div>
            </div>
        @endforeach
    </div>
@endif
@endsection
