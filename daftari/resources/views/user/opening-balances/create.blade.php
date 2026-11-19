@extends('layouts.app')

@section('title', __('Opening Balances'))

@section('content')
@include('user.accounting.partials.tabs')

<div class="mb-6">
    <h2 class="text-lg font-semibold text-slate-900">{{ __('Opening Balances') }}</h2>
    <p class="text-sm text-slate-500 mt-1 max-w-2xl">{{ __('For a company onboarding mid-year — enter each account\'s balance as of a chosen date. Any difference between what you enter is automatically posted to Owner\'s Equity, so you don\'t need to work out the double-entry yourself.') }}</p>
</div>

@if ($errors->any())
    <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
        <ul class="list-disc ps-4">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@if ($existing)
    <div class="bg-white rounded-xl border border-amber-200 p-6 max-w-2xl">
        <p class="text-sm text-slate-700">{{ __('Opening balances have already been recorded for this company, on :date.', ['date' => $existing->entry_date->format('Y-m-d')]) }}</p>
        <a href="{{ route('app.journals.show', $existing) }}" class="mt-3 inline-block text-sm font-semibold text-brand-700 hover:underline">{{ __('View that entry') }}</a>
        <p class="text-xs text-slate-400 mt-3">{{ __('To correct it, post a manual journal entry instead — this can only be done once per company.') }}</p>
    </div>
@else
    <form method="POST" action="{{ route('app.opening-balances.store') }}" class="space-y-6">
        @csrf

        <div class="bg-white rounded-xl border border-slate-100 p-6 max-w-md">
            <label class="block text-xs font-medium text-slate-500">{{ __('As of date') }}</label>
            <input type="date" name="as_of_date" value="{{ old('as_of_date', now()->toDateString()) }}" required class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        </div>

        <div class="bg-white rounded-xl border border-slate-100 overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-slate-500 border-b border-slate-100">
                        <th class="px-5 py-2.5 font-medium">{{ __('Code') }}</th>
                        <th class="px-5 py-2.5 font-medium">{{ __('Account') }}</th>
                        <th class="px-5 py-2.5 font-medium w-40 text-end">{{ __('Debit') }}</th>
                        <th class="px-5 py-2.5 font-medium w-40 text-end">{{ __('Credit') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($accounts as $i => $account)
                        <tr class="border-b border-slate-50 last:border-0">
                            <td class="px-5 py-2 text-slate-500">{{ $account->code }}</td>
                            <td class="px-5 py-2 text-slate-800">
                                {{ $account->name }}
                                <input type="hidden" name="lines[{{ $i }}][account_id]" value="{{ $account->id }}">
                            </td>
                            <td class="px-5 py-2"><input type="number" step="0.01" min="0" name="lines[{{ $i }}][debit]" value="{{ old("lines.$i.debit") }}" class="w-full rounded-lg border border-slate-200 text-sm text-end focus:border-brand-500 focus:ring-brand-500"></td>
                            <td class="px-5 py-2"><input type="number" step="0.01" min="0" name="lines[{{ $i }}][credit]" value="{{ old("lines.$i.credit") }}" class="w-full rounded-lg border border-slate-200 text-sm text-end focus:border-brand-500 focus:ring-brand-500"></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <button type="submit" class="rounded-lg bg-brand-600 px-5 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Record opening balances') }}</button>
    </form>
@endif
@endsection
