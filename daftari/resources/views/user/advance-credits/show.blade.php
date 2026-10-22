@extends('layouts.app')

@section('title', $credit->name)

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <a href="{{ route('app.advance-credits.index') }}" class="text-xs text-slate-400 hover:text-slate-600">&larr; {{ __('Advance Credits') }}</a>
        <h2 class="text-lg font-semibold text-slate-900 mt-1">{{ $credit->name }}</h2>
        <p class="text-sm text-slate-500 mt-0.5">
            {{ $credit->client->name }}
            @if ($credit->sourceInvoice)
                &middot; <a href="{{ route('app.invoices.show', $credit->sourceInvoice) }}" class="text-brand-700 hover:underline">{{ $credit->sourceInvoice->invoice_number }}</a>
            @endif
        </p>
        @if ($credit->notes)<p class="text-sm text-slate-500 mt-1">{{ $credit->notes }}</p>@endif
    </div>
    <form method="POST" action="{{ route('app.advance-credits.destroy', $credit) }}" onsubmit="return confirm('{{ __('Delete this advance credit and every portion/application in it? This cannot be undone.') }}')">
        @csrf @method('DELETE')
        <button type="submit" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-red-600 hover:border-red-300">{{ __('Delete') }}</button>
    </form>
</div>

<div class="grid sm:grid-cols-2 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-slate-100 p-4">
        <p class="text-xs text-slate-400">{{ __('Total advance') }}</p>
        <p class="text-lg font-bold text-slate-900 mt-1">{{ \App\Support\Money::format($credit->totalAmount()) }}</p>
    </div>
    <div class="bg-white rounded-xl border border-slate-100 p-4">
        <p class="text-xs text-slate-400">{{ __('Remaining (not yet applied)') }}</p>
        <p class="text-lg font-bold text-amber-600 mt-1">{{ \App\Support\Money::format($credit->remainingTotal()) }}</p>
    </div>
</div>

<div class="bg-white rounded-xl border border-slate-100 overflow-hidden">
    <div class="px-5 py-3 border-b border-slate-100">
        <h3 class="text-sm font-semibold text-slate-900">{{ __('Portions') }}</h3>
    </div>
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-slate-500 border-b border-slate-100">
                <th class="px-5 py-2.5 font-medium">{{ __('Description') }}</th>
                <th class="px-5 py-2.5 font-medium text-right">{{ __('Total') }}</th>
                <th class="px-5 py-2.5 font-medium text-right">{{ __('Applied') }}</th>
                <th class="px-5 py-2.5 font-medium text-right">{{ __('Remaining') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($credit->portions as $portion)
                <tr class="border-b border-slate-50 last:border-0 align-top">
                    <td class="px-5 py-2.5 text-slate-800">{{ $portion->description }}</td>
                    <td class="px-5 py-2.5 text-right tabular-nums">{{ \App\Support\Money::format($portion->total) }}</td>
                    <td class="px-5 py-2.5 text-right tabular-nums text-slate-500">{{ \App\Support\Money::format($portion->appliedTotal()) }}</td>
                    <td class="px-5 py-2.5 text-right tabular-nums font-semibold {{ $portion->remainingTotal() > 0 ? 'text-amber-600' : 'text-slate-400' }}">{{ \App\Support\Money::format($portion->remainingTotal()) }}</td>
                </tr>
                @if ($portion->applications->isNotEmpty())
                    <tr class="border-b border-slate-50 last:border-0 bg-slate-50">
                        <td colspan="4" class="px-5 py-2 text-xs text-slate-500">
                            @foreach ($portion->applications as $application)
                                <div class="flex items-center justify-between py-0.5">
                                    <span>{{ __('Applied to') }} <a href="{{ route('app.invoices.show', $application->invoice) }}" class="text-brand-700 hover:underline">{{ $application->invoice->invoice_number }}</a> {{ __('on') }} {{ $application->created_at->format('Y-m-d') }}</span>
                                    <span class="font-medium">{{ \App\Support\Money::format($application->total) }}</span>
                                </div>
                            @endforeach
                        </td>
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>
</div>
@endsection
