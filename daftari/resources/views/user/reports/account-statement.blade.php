@extends('layouts.app')

@section('title', __('Account Statement'))

@section('content')
<div class="mb-6">
    <h2 class="text-lg font-semibold text-slate-900">{{ __('Account Statement') }}</h2>
    <p class="text-sm text-slate-500 mt-1">{{ __('Invoices and payments for a single customer, with a running balance.') }}</p>
</div>

@include('user.reports.partials.period-selector', ['extra' => ['client_id' => request('client_id')]])

<form method="GET" class="bg-white rounded-xl border border-slate-100 p-4 mb-6 flex items-center gap-3">
    <input type="hidden" name="period" value="{{ $period['preset'] }}">
    <input type="hidden" name="from" value="{{ $period['from']->toDateString() }}">
    <input type="hidden" name="to" value="{{ $period['to']->toDateString() }}">
    <select name="client_id" onchange="this.form.submit()" class="rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500 min-w-[16rem]">
        <option value="">{{ __('Select a customer') }}</option>
        @foreach ($clients as $c)<option value="{{ $c->id }}" @selected($client && $client->id === $c->id)>{{ $c->name }}</option>@endforeach
    </select>
</form>

@if ($client)
    <div class="bg-white rounded-xl border border-slate-100">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="px-5 py-3 font-medium">{{ __('Date') }}</th>
                    <th class="px-5 py-3 font-medium">{{ __('Description') }}</th>
                    <th class="px-5 py-3 font-medium text-end">{{ __('Debit') }}</th>
                    <th class="px-5 py-3 font-medium text-end">{{ __('Credit') }}</th>
                    <th class="px-5 py-3 font-medium text-end">{{ __('Balance') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr class="border-b border-slate-50 text-slate-400 italic">
                    <td class="px-5 py-3" colspan="4">{{ __('Opening balance') }}</td>
                    <td class="px-5 py-3 text-end">{{ number_format($openingBalance, 2) }}</td>
                </tr>
                @forelse ($lines as $line)
                    <tr class="border-b border-slate-50 last:border-0">
                        <td class="px-5 py-3 text-slate-500">{{ $line->date->format('Y-m-d') }}</td>
                        <td class="px-5 py-3">{{ $line->description }}</td>
                        <td class="px-5 py-3 text-end">{{ $line->debit > 0 ? number_format($line->debit, 2) : '' }}</td>
                        <td class="px-5 py-3 text-end">{{ $line->credit > 0 ? number_format($line->credit, 2) : '' }}</td>
                        <td class="px-5 py-3 text-end font-medium">{{ number_format($line->balance, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-10 text-center text-slate-400">{{ __('No activity for this customer in the selected period.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@else
    <div class="bg-white rounded-xl border border-slate-100 px-6 py-16 text-center text-sm text-slate-400">{{ __('Select a customer to see their statement.') }}</div>
@endif
@endsection
