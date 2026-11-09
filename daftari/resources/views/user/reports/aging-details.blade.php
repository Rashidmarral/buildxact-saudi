@extends('layouts.app')

@section('title', __('Aged Receivables/Payables (Details)'))

@section('content')
<div class="flex items-start justify-between mb-6">
    <div>
        <h2 class="text-lg font-semibold text-slate-900">{{ __('Aged Receivables/Payables (Details)') }}</h2>
        <p class="text-sm text-slate-500 mt-1">{{ __('Every outstanding invoice or bill behind the aging buckets, one row each — as of today.') }}</p>
    </div>
    <div class="flex items-center gap-2 shrink-0">
        <a href="{{ route('app.reports.aging-details', ['type' => $type, 'export' => 'csv']) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download CSV') }}</a>
        <a href="{{ route('app.reports.aging-details', ['type' => $type, 'export' => 'pdf']) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download PDF') }}</a>
    </div>
</div>

<div class="flex items-center gap-2 mb-6 text-sm border-b border-slate-100">
    <a href="{{ route('app.reports.aging-details', ['type' => 'receivables']) }}" class="px-3 py-2 border-b-2 {{ $type === 'receivables' ? 'border-brand-600 text-brand-700 font-semibold' : 'border-transparent text-slate-500 hover:text-slate-700' }}">{{ __('Receivables (customers owe you)') }}</a>
    <a href="{{ route('app.reports.aging-details', ['type' => 'payables']) }}" class="px-3 py-2 border-b-2 {{ $type === 'payables' ? 'border-brand-600 text-brand-700 font-semibold' : 'border-transparent text-slate-500 hover:text-slate-700' }}">{{ __('Payables (you owe suppliers)') }}</a>
</div>

<div class="bg-white rounded-xl border border-slate-100 overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-slate-500 border-b border-slate-100">
                <th class="px-5 py-3 font-medium">{{ __('Date') }}</th>
                <th class="px-5 py-3 font-medium">{{ __('Transaction Type') }}</th>
                <th class="px-5 py-3 font-medium">{{ $type === 'receivables' ? __('Invoice No.') : __('Reference No') }}</th>
                <th class="px-5 py-3 font-medium">{{ $type === 'receivables' ? __('Customer') : __('Supplier') }}</th>
                <th class="px-5 py-3 font-medium">{{ __('Due Date') }}</th>
                <th class="px-5 py-3 font-medium text-end">{{ __('Due') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr class="border-b border-slate-50 last:border-0">
                    <td class="px-5 py-3 text-slate-500">{{ $row['date']->format('Y-m-d') }}</td>
                    <td class="px-5 py-3">{{ $row['transactionType'] }}</td>
                    <td class="px-5 py-3 font-medium text-slate-800">{{ $row['reference'] }}</td>
                    <td class="px-5 py-3">{{ $row['party'] }}</td>
                    <td class="px-5 py-3 text-slate-500">{{ $row['dueDate']->format('Y-m-d') }}</td>
                    <td class="px-5 py-3 text-end font-semibold {{ $row['dueDate']->isPast() ? 'text-red-600' : 'text-slate-900' }}">{{ \App\Support\Money::format($row['due']) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-5 py-10 text-center text-slate-400">{{ $type === 'receivables' ? __('No outstanding customer invoices.') : __('No outstanding supplier bills.') }}</td></tr>
            @endforelse
        </tbody>
        @if ($rows->isNotEmpty())
            <tfoot>
                <tr class="border-t-2 border-slate-800 font-semibold text-slate-900">
                    <td class="px-5 py-3" colspan="5">{{ __('Total') }}</td>
                    <td class="px-5 py-3 text-end">{{ \App\Support\Money::format($totalDue) }}</td>
                </tr>
            </tfoot>
        @endif
    </table>
</div>
@endsection
