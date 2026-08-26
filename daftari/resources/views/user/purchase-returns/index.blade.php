@extends('layouts.app')

@section('title', __('Purchase returns'))

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">{{ __('Purchase returns') }}</h1>
        <p class="text-sm text-slate-500 mt-1">{{ __('Goods returned or amounts reduced against posted bills.') }}</p>
    </div>
    <a href="{{ route('app.purchase-returns.create') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('+ New purchase return') }}</a>
</div>

<div class="bg-white rounded-xl border border-slate-100">
    @if ($purchaseReturns->isEmpty())
        <p class="px-6 py-16 text-center text-sm text-slate-500">{{ __('No purchase returns yet.') }}</p>
    @else
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="px-6 py-3 font-medium">{{ __('Number') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Date') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Bill') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Supplier') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Total') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Status') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($purchaseReturns as $purchaseReturn)
                    <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50 cursor-pointer" onclick="window.location='{{ route('app.purchase-returns.show', $purchaseReturn) }}'">
                        <td class="px-6 py-3 font-medium text-brand-700">{{ $purchaseReturn->return_number }}</td>
                        <td class="px-6 py-3">{{ $purchaseReturn->issue_date->format('Y-m-d') }}</td>
                        <td class="px-6 py-3">{{ $purchaseReturn->bill->bill_number }}</td>
                        <td class="px-6 py-3">{{ $purchaseReturn->supplier->name }}</td>
                        <td class="px-6 py-3">{{ \App\Support\Money::format($purchaseReturn->total) }}</td>
                        <td class="px-6 py-3">
                            @if ($purchaseReturn->status === 'void')
                                <span class="inline-block rounded-full bg-red-50 text-red-600 text-xs font-medium px-2.5 py-1">{{ __('Void') }}</span>
                            @else
                                <span class="inline-block rounded-full bg-brand-50 text-brand-700 text-xs font-medium px-2.5 py-1">{{ __('Issued') }}</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
<div class="mt-4">{{ $purchaseReturns->links() }}</div>
@endsection
