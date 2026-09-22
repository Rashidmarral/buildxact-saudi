@extends('layouts.app')

@section('title', $project->name)

@php
    $revenue = $project->revenue();
    $costs = $project->costs();
    $margin = $project->margin();
    $marginPct = $project->marginPercent();
@endphp

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <div class="flex items-center gap-3">
            <h2 class="text-lg font-semibold text-slate-900">{{ $project->name }}</h2>
            <span class="text-xs font-mono text-slate-400">{{ $project->code }}</span>
        </div>
        @if ($project->client)<p class="text-sm text-slate-500 mt-1">{{ $project->client->display_name }}</p>@endif
    </div>
    <div class="flex items-center gap-3">
        <a href="{{ route('app.projects.edit', $project) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Edit') }}</a>
        <form method="POST" action="{{ route('app.projects.destroy', $project) }}" onsubmit="return confirm('{{ __('Delete this project?') }}')">
            @csrf @method('DELETE')
            <button type="submit" class="rounded-lg border border-red-200 px-4 py-2 text-sm font-semibold text-red-600 hover:bg-red-50">{{ __('Delete') }}</button>
        </form>
    </div>
</div>

<div class="grid sm:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-slate-100 p-5">
        <p class="text-xs text-slate-400">{{ __('Revenue') }}</p>
        <p class="text-xl font-bold text-slate-900 mt-1">{{ \App\Support\Money::format($revenue) }}</p>
        @if ($project->target_revenue)<p class="text-xs text-slate-400 mt-1">{{ __('Target') }}: {{ \App\Support\Money::format($project->target_revenue) }}</p>@endif
    </div>
    <div class="bg-white rounded-xl border border-slate-100 p-5">
        <p class="text-xs text-slate-400">{{ __('Costs') }}</p>
        <p class="text-xl font-bold text-slate-900 mt-1">{{ \App\Support\Money::format($costs) }}</p>
        @if ($project->cost_ceiling)<p class="text-xs {{ $costs > $project->cost_ceiling ? 'text-red-600' : 'text-slate-400' }} mt-1">{{ __('Ceiling') }}: {{ \App\Support\Money::format($project->cost_ceiling) }}</p>@endif
    </div>
    <div class="bg-white rounded-xl border border-slate-100 p-5">
        <p class="text-xs text-slate-400">{{ __('Margin') }}</p>
        <p class="text-xl font-bold {{ $margin >= 0 ? 'text-emerald-600' : 'text-red-600' }} mt-1">{{ \App\Support\Money::format($margin) }}</p>
    </div>
    <div class="bg-white rounded-xl border border-slate-100 p-5">
        <p class="text-xs text-slate-400">{{ __('Margin %') }}</p>
        <p class="text-xl font-bold {{ ($marginPct ?? 0) >= 0 ? 'text-emerald-600' : 'text-red-600' }} mt-1">{{ $marginPct !== null ? number_format($marginPct, 1).'%' : '—' }}</p>
    </div>
</div>

<div class="grid md:grid-cols-2 gap-6">
    <div class="bg-white rounded-xl border border-slate-100 p-6">
        <h3 class="font-semibold text-slate-900 mb-4">{{ __('Linked invoices') }}</h3>
        @if ($project->invoices->isEmpty())
            <p class="text-sm text-slate-400">{{ __('No invoices linked to this project yet.') }}</p>
        @else
            <table class="w-full text-sm">
                <tbody>
                    @foreach ($project->invoices as $invoice)
                        <tr class="border-b border-slate-50 last:border-0">
                            <td class="py-2"><a href="{{ route('app.invoices.show', $invoice) }}" class="text-brand-700 hover:underline">{{ $invoice->invoice_number }}</a></td>
                            <td class="py-2 text-slate-500">{{ $invoice->client->display_name ?? '—' }}</td>
                            <td class="py-2 text-end">{{ \App\Support\Money::format($invoice->total) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
    <div class="bg-white rounded-xl border border-slate-100 p-6">
        <h3 class="font-semibold text-slate-900 mb-4">{{ __('Linked quotations') }}</h3>
        @if ($project->quotations->isEmpty())
            <p class="text-sm text-slate-400">{{ __('No quotations linked to this project yet.') }}</p>
        @else
            <table class="w-full text-sm">
                <tbody>
                    @foreach ($project->quotations as $quotation)
                        <tr class="border-b border-slate-50 last:border-0">
                            <td class="py-2"><a href="{{ route('app.quotations.show', $quotation) }}" class="text-brand-700 hover:underline">{{ $quotation->quotation_number }}</a></td>
                            <td class="py-2 text-slate-500">{{ $quotation->client->display_name ?? '—' }}</td>
                            <td class="py-2 text-end">{{ \App\Support\Money::format($quotation->total) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
    <div class="bg-white rounded-xl border border-slate-100 p-6">
        <h3 class="font-semibold text-slate-900 mb-4">{{ __('Linked purchase orders (sub-vendors)') }}</h3>
        @if ($project->purchaseOrders->isEmpty())
            <p class="text-sm text-slate-400">{{ __('No purchase orders linked to this project yet.') }}</p>
        @else
            <table class="w-full text-sm">
                <tbody>
                    @foreach ($project->purchaseOrders as $purchaseOrder)
                        <tr class="border-b border-slate-50 last:border-0">
                            <td class="py-2"><a href="{{ route('app.purchase-orders.show', $purchaseOrder) }}" class="text-brand-700 hover:underline">{{ $purchaseOrder->po_number }}</a></td>
                            <td class="py-2 text-slate-500">{{ $purchaseOrder->supplier->display_name ?? '—' }}</td>
                            <td class="py-2 text-end">{{ \App\Support\Money::format($purchaseOrder->total) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
    <div class="bg-white rounded-xl border border-slate-100 p-6">
        <h3 class="font-semibold text-slate-900 mb-4">{{ __('Linked bills') }}</h3>
        @if ($project->bills->isEmpty())
            <p class="text-sm text-slate-400">{{ __('No bills linked to this project yet.') }}</p>
        @else
            <table class="w-full text-sm">
                <tbody>
                    @foreach ($project->bills as $bill)
                        <tr class="border-b border-slate-50 last:border-0">
                            <td class="py-2"><a href="{{ route('app.bills.show', $bill) }}" class="text-brand-700 hover:underline">{{ $bill->bill_number }}</a></td>
                            <td class="py-2 text-slate-500">{{ $bill->supplier->display_name ?? '—' }}</td>
                            <td class="py-2 text-end">{{ \App\Support\Money::format($bill->total) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
    <div class="bg-white rounded-xl border border-slate-100 p-6">
        <h3 class="font-semibold text-slate-900 mb-4">{{ __('Linked expenses') }}</h3>
        @if ($project->expenses->isEmpty())
            <p class="text-sm text-slate-400">{{ __('No expenses linked to this project yet.') }}</p>
        @else
            <table class="w-full text-sm">
                <tbody>
                    @foreach ($project->expenses as $expense)
                        <tr class="border-b border-slate-50 last:border-0">
                            <td class="py-2">{{ $expense->description ?: ($expense->category->name ?? '—') }}</td>
                            <td class="py-2 text-slate-500">{{ $expense->expense_date->format('Y-m-d') }}</td>
                            <td class="py-2 text-end">{{ \App\Support\Money::format($expense->amount) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
@endsection
