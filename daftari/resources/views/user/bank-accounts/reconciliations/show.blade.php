@extends('layouts.app')

@section('title', __('Reconciliation'))

@section('content')
@php
    $unmatchedLines = $reconciliation->lines->whereNull('matched_type');
    $matchedLines = $reconciliation->lines->whereNotNull('matched_type');
    $isDone = $reconciliation->status === 'completed';
@endphp

<div class="flex items-start justify-between mb-6">
    <div>
        <h1 class="text-lg font-semibold text-slate-900">{{ __('Reconcile :account', ['account' => $reconciliation->bankAccount->name]) }}</h1>
        <p class="text-sm text-slate-500 mt-1">{{ __('Statement date :date, ending balance :balance', ['date' => $reconciliation->statement_date->format('Y-m-d'), 'balance' => \App\Support\Money::format($reconciliation->statement_ending_balance)]) }}</p>
    </div>
    <a href="{{ route('app.bank-reconciliations.index', $reconciliation->bank_account_id) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Back') }}</a>
</div>

@if (session('status'))
    <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
@endif
@if ($errors->any())
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
@endif
@if (session('import_errors') && count(session('import_errors')))
    <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
        <ul class="list-disc ps-4 space-y-1">
            @foreach (session('import_errors') as $err)<li>{{ $err }}</li>@endforeach
        </ul>
    </div>
@endif

@if ($isDone)
    <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ __('This reconciliation is completed.') }}</div>
@elseif ($unmatchedLines->isEmpty() && $candidates->isEmpty())
    <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 flex items-center justify-between">
        <span>{{ __('Everything is matched — ready to complete.') }}</span>
        <form method="POST" action="{{ route('app.bank-reconciliations.complete', $reconciliation) }}">
            @csrf
            <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Mark reconciliation complete') }}</button>
        </form>
    </div>
@else
    <div class="mb-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700 flex items-center justify-between flex-wrap gap-3">
        <span>{{ __(':unmatched statement line(s) and :outstanding book transaction(s) still need matching.', ['unmatched' => $unmatchedLines->count(), 'outstanding' => $candidates->count()]) }}</span>
        <form method="POST" action="{{ route('app.bank-reconciliations.complete', $reconciliation) }}" onsubmit="return confirm('{{ __('Complete this reconciliation with unmatched items remaining?') }}')">
            @csrf
            <button type="submit" class="rounded-lg border border-amber-300 px-4 py-2 text-sm font-semibold text-amber-800 hover:bg-amber-100">{{ __('Complete anyway') }}</button>
        </form>
    </div>
@endif

@unless ($isDone)
    <div class="bg-white rounded-xl border border-slate-100 p-6 mb-6">
        <h2 class="font-semibold text-slate-900 mb-1">{{ __('Import statement') }}</h2>
        <p class="text-xs text-slate-500 mb-4">{{ __('CSV with columns: date, description, reference (optional), amount. Positive amounts are deposits, negative are withdrawals.') }}</p>
        <form method="POST" action="{{ route('app.bank-reconciliations.import', $reconciliation) }}" enctype="multipart/form-data" class="flex items-center gap-3">
            @csrf
            <input type="file" name="file" accept=".csv,.txt" required class="flex-1 text-sm">
            <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700 whitespace-nowrap">{{ __('Import lines') }}</button>
        </form>
    </div>
@endunless

<div class="grid lg:grid-cols-2 gap-6">
    <div class="bg-white rounded-xl border border-slate-100">
        <div class="px-5 py-3 border-b border-slate-100 font-semibold text-slate-900 text-sm">{{ __('Statement lines') }}</div>
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="px-5 py-2 font-medium">{{ __('Date') }}</th>
                    <th class="px-5 py-2 font-medium">{{ __('Description') }}</th>
                    <th class="px-5 py-2 font-medium text-end">{{ __('Amount') }}</th>
                    <th class="px-5 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($reconciliation->lines as $line)
                    <tr class="border-b border-slate-50 last:border-0">
                        <td class="px-5 py-2 text-slate-500">{{ $line->date->format('Y-m-d') }}</td>
                        <td class="px-5 py-2">{{ $line->description }}</td>
                        <td class="px-5 py-2 text-end {{ $line->amount >= 0 ? 'text-emerald-600' : 'text-red-600' }}">{{ \App\Support\Money::format($line->amount) }}</td>
                        <td class="px-5 py-2 text-end">
                            @if ($line->isMatched())
                                <span class="text-emerald-600 text-xs">{{ __('Matched') }}</span>
                                @unless ($isDone)
                                    <form method="POST" action="{{ route('app.bank-statement-lines.unmatch', $line) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="text-xs text-slate-400 hover:text-red-600 ms-2">{{ __('Undo') }}</button>
                                    </form>
                                @endunless
                            @elseif (! $isDone)
                                <form method="POST" action="{{ route('app.bank-statement-lines.match', $line) }}" class="inline-flex items-center gap-1">
                                    @csrf
                                    <select name="matched_id_composite" onchange="this.form.matched_type.value=this.value.split(':')[0]; this.form.matched_id.value=this.value.split(':')[1];" class="rounded-lg border border-slate-200 text-xs focus:border-brand-500 focus:ring-brand-500">
                                        <option value="">{{ __('Match to…') }}</option>
                                        @foreach ($candidates->where('amount', (float) $line->amount) as $c)
                                            <option value="{{ $c['type'] }}:{{ $c['id'] }}">{{ $c['label'] }}</option>
                                        @endforeach
                                    </select>
                                    <input type="hidden" name="matched_type" value="">
                                    <input type="hidden" name="matched_id" value="">
                                    <button type="submit" class="text-xs text-brand-700 hover:underline">{{ __('Match') }}</button>
                                </form>
                            @else
                                <span class="text-xs text-slate-400">{{ __('Unmatched') }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-5 py-8 text-center text-slate-400">{{ __('No statement lines imported yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="bg-white rounded-xl border border-slate-100">
        <div class="px-5 py-3 border-b border-slate-100 font-semibold text-slate-900 text-sm">{{ __('Outstanding book transactions') }}</div>
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="px-5 py-2 font-medium">{{ __('Date') }}</th>
                    <th class="px-5 py-2 font-medium">{{ __('Description') }}</th>
                    <th class="px-5 py-2 font-medium text-end">{{ __('Amount') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($candidates as $c)
                    <tr class="border-b border-slate-50 last:border-0">
                        <td class="px-5 py-2 text-slate-500">{{ $c['date']->format('Y-m-d') }}</td>
                        <td class="px-5 py-2">{{ $c['label'] }}</td>
                        <td class="px-5 py-2 text-end {{ $c['amount'] >= 0 ? 'text-emerald-600' : 'text-red-600' }}">{{ \App\Support\Money::format($c['amount']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-5 py-8 text-center text-slate-400">{{ __('Nothing outstanding — every recorded transaction is matched.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
