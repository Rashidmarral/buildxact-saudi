@extends('layouts.app')

@section('title', __('Advance Credits'))

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h2 class="text-lg font-semibold text-slate-900">{{ __('Advance Credits') }}</h2>
        <p class="text-sm text-slate-500 mt-1 max-w-2xl">{{ __('Advance payments received (whether or not they were issued through the dedicated Prepayment Invoice feature), split into portions you can credit against future invoices for the same client — in full, or a little at a time.') }}</p>
    </div>
    <a href="{{ route('app.advance-credits.create') }}" class="shrink-0 rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('+ New advance credit') }}</a>
</div>

<div class="bg-white rounded-xl border border-slate-100 overflow-hidden">
    @if ($credits->isEmpty())
        <div class="px-6 py-16 text-center">
            <p class="text-sm text-slate-500 mb-4">{{ __('No advance credits yet.') }}</p>
            <a href="{{ route('app.advance-credits.create') }}" class="inline-block rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('+ New advance credit') }}</a>
        </div>
    @else
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="px-6 py-3 font-medium">{{ __('Name') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Client') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Source invoice') }}</th>
                    <th class="px-6 py-3 font-medium text-right">{{ __('Total') }}</th>
                    <th class="px-6 py-3 font-medium text-right">{{ __('Remaining') }}</th>
                    <th class="px-6 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($credits as $credit)
                    <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50">
                        <td class="px-6 py-3 font-medium text-slate-900">
                            <a href="{{ route('app.advance-credits.show', $credit) }}" class="hover:underline">{{ $credit->name }}</a>
                        </td>
                        <td class="px-6 py-3 text-slate-600">{{ $credit->client->name }}</td>
                        <td class="px-6 py-3 text-slate-500">{{ $credit->sourceInvoice?->invoice_number ?: '—' }}</td>
                        <td class="px-6 py-3 text-right tabular-nums">{{ \App\Support\Money::format($credit->totalAmount()) }}</td>
                        <td class="px-6 py-3 text-right tabular-nums font-semibold {{ $credit->remainingTotal() > 0 ? 'text-amber-600' : 'text-slate-400' }}">{{ \App\Support\Money::format($credit->remainingTotal()) }}</td>
                        <td class="px-6 py-3 text-right">
                            <a href="{{ route('app.advance-credits.show', $credit) }}" class="text-brand-700 hover:underline text-xs font-semibold">{{ __('Open') }}</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
@endsection
