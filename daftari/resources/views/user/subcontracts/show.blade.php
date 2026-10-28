@extends('layouts.app')

@section('title', $subcontract->supplier->name.' — '.$subcontract->project->name)

@section('content')
@php
    $totalPaid = $subcontract->totalPaid();
    $balanceBeforeRetention = $subcontract->balanceBeforeRetention();
    $retentionAmount = $subcontract->retentionAmount();
    $balanceAfterRetention = $subcontract->balanceAfterRetention();
@endphp

<div class="flex items-start justify-between mb-6">
    <div>
        <h2 class="text-lg font-semibold text-slate-900">{{ $subcontract->title ?: __('Subcontract') }}</h2>
        <p class="text-sm text-slate-500 mt-1">{{ $subcontract->supplier->name }} — {{ $subcontract->project->name }} @if($subcontract->contract_number) · {{ $subcontract->contract_number }} @endif</p>
    </div>
    <div class="flex items-center gap-2">
        <a href="{{ route('app.subcontracts.report.pdf', $subcontract) }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Receiving Payments Report (PDF)') }}</a>
        <a href="{{ route('app.subcontracts.edit', $subcontract) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Edit') }}</a>
    </div>
</div>

@if (session('status'))
    <div class="mb-4 rounded-lg border border-teal-200 bg-teal-50 px-4 py-3 text-sm text-teal-700">{{ session('status') }}</div>
@endif

<div class="grid sm:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-slate-100 p-5">
        <p class="text-xs font-semibold uppercase text-slate-500">{{ __('Contract value') }}</p>
        <p class="text-xl font-semibold text-slate-900 mt-1">{{ number_format((float) $subcontract->contract_value, 2) }}</p>
    </div>
    <div class="bg-white rounded-xl border border-slate-100 p-5">
        <p class="text-xs font-semibold uppercase text-slate-500">{{ __('Received to date') }}</p>
        <p class="text-xl font-semibold text-teal-700 mt-1">{{ number_format($totalPaid, 2) }}</p>
    </div>
    <div class="bg-white rounded-xl border border-slate-100 p-5">
        <p class="text-xs font-semibold uppercase text-slate-500">{{ __('Retention held') }} ({{ number_format((float) $subcontract->retention_percent, 2) }}%)</p>
        <p class="text-xl font-semibold text-amber-600 mt-1">{{ number_format($retentionAmount, 2) }}</p>
    </div>
    <div class="bg-white rounded-xl border border-slate-100 p-5">
        <p class="text-xs font-semibold uppercase text-slate-500">{{ __('Balance after retention') }}</p>
        <p class="text-xl font-semibold {{ $balanceAfterRetention > 0 ? 'text-amber-600' : 'text-slate-900' }} mt-1">{{ number_format($balanceAfterRetention, 2) }}</p>
    </div>
</div>

@if ($subcontract->boqItems->isNotEmpty())
    <div class="bg-white rounded-xl border border-slate-100 mb-6">
        <div class="px-5 py-3 border-b border-slate-100"><h3 class="text-sm font-semibold text-slate-900">{{ __('BOQ breakdown') }}</h3></div>
        <table class="w-full text-sm">
            <thead><tr class="text-left text-slate-500 border-b border-slate-100">
                <th class="px-5 py-2.5 font-medium">{{ __('Description') }}</th>
                <th class="px-5 py-2.5 font-medium text-end">{{ __('Quantity') }}</th>
                <th class="px-5 py-2.5 font-medium">{{ __('Unit') }}</th>
                <th class="px-5 py-2.5 font-medium text-end">{{ __('Rate') }}</th>
                <th class="px-5 py-2.5 font-medium text-end">{{ __('Amount') }}</th>
            </tr></thead>
            <tbody>
                @foreach ($subcontract->boqItems as $item)
                    <tr class="border-b border-slate-50 last:border-0">
                        <td class="px-5 py-2.5">{{ $item->description }}</td>
                        <td class="px-5 py-2.5 text-end">{{ $item->quantity !== null ? number_format((float) $item->quantity, 2) : '—' }}</td>
                        <td class="px-5 py-2.5">{{ $item->unit ?: '—' }}</td>
                        <td class="px-5 py-2.5 text-end">{{ $item->rate !== null ? number_format((float) $item->rate, 2) : '—' }}</td>
                        <td class="px-5 py-2.5 text-end font-medium">{{ number_format((float) $item->amount, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

<div class="bg-white rounded-xl border border-slate-100 mb-6">
    <div class="px-5 py-3 border-b border-slate-100"><h3 class="text-sm font-semibold text-slate-900">{{ __('Milestone schedule') }}</h3></div>
    <table class="w-full text-sm">
        <thead><tr class="text-left text-slate-500 border-b border-slate-100">
            <th class="px-5 py-2.5 font-medium">{{ __('Milestone') }}</th>
            <th class="px-5 py-2.5 font-medium text-end">{{ __('%') }}</th>
            <th class="px-5 py-2.5 font-medium text-end">{{ __('Amount') }}</th>
            <th class="px-5 py-2.5 font-medium text-end">{{ __('Received') }}</th>
            <th class="px-5 py-2.5 font-medium text-end">{{ __('Balance') }}</th>
            <th class="px-5 py-2.5 font-medium">{{ __('Status') }}</th>
        </tr></thead>
        <tbody>
            @foreach ($subcontract->milestones as $milestone)
                <tr class="border-b border-slate-50 last:border-0">
                    <td class="px-5 py-2.5">{{ $milestone->name }}</td>
                    <td class="px-5 py-2.5 text-end">{{ number_format((float) $milestone->percentage, 2) }}%</td>
                    <td class="px-5 py-2.5 text-end">{{ number_format((float) $milestone->amount, 2) }}</td>
                    <td class="px-5 py-2.5 text-end text-teal-700">{{ number_format($milestone->paidAmount(), 2) }}</td>
                    <td class="px-5 py-2.5 text-end font-medium">{{ number_format($milestone->balance(), 2) }}</td>
                    <td class="px-5 py-2.5">
                        @if ($milestone->requires_handover_approval)
                            <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $milestone->approved_at ? 'bg-teal-50 text-teal-700' : 'bg-amber-50 text-amber-700' }}">
                                {{ $milestone->approved_at ? __('Approved') : __('Awaiting handover approval') }}
                            </span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

<div class="bg-white rounded-xl border border-slate-100 mb-6">
    <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between">
        <h3 class="text-sm font-semibold text-slate-900">{{ __('Payments') }}</h3>
    </div>
    <table class="w-full text-sm">
        <thead><tr class="text-left text-slate-500 border-b border-slate-100">
            <th class="px-5 py-2.5 font-medium">{{ __('Date') }}</th>
            <th class="px-5 py-2.5 font-medium">{{ __('Milestone') }}</th>
            <th class="px-5 py-2.5 font-medium">{{ __('Source') }}</th>
            <th class="px-5 py-2.5 font-medium">{{ __('Reference') }}</th>
            <th class="px-5 py-2.5 font-medium text-end">{{ __('Amount') }}</th>
            <th class="px-5 py-2.5 font-medium"></th>
        </tr></thead>
        <tbody>
            @forelse ($subcontract->payments as $payment)
                <tr class="border-b border-slate-50 last:border-0">
                    <td class="px-5 py-2.5">{{ $payment->paid_at->format('Y-m-d') }}</td>
                    <td class="px-5 py-2.5">{{ $payment->milestone->name ?? '—' }}</td>
                    <td class="px-5 py-2.5">
                        @if ($payment->payment_voucher_id)
                            <a href="{{ route('app.payment-vouchers.show', $payment->payment_voucher_id) }}" class="text-brand-700 hover:underline">{{ $payment->paymentVoucher->voucher_number }}</a>
                        @else
                            <span class="text-slate-400">{{ __('Manual entry') }}</span>
                        @endif
                    </td>
                    <td class="px-5 py-2.5">{{ $payment->reference ?: '—' }}</td>
                    <td class="px-5 py-2.5 text-end font-medium">{{ number_format((float) $payment->amount, 2) }}</td>
                    <td class="px-5 py-2.5 text-end">
                        <form method="POST" action="{{ route('app.subcontracts.payments.destroy', [$subcontract, $payment]) }}" onsubmit="return confirm('{{ __('Remove this payment from the tracker? The underlying voucher, if any, is not affected.') }}')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-xs text-red-500 hover:underline">{{ __('Remove') }}</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-5 py-8 text-center text-slate-400">{{ __('No payments recorded yet.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="grid sm:grid-cols-2 gap-6">
    <div class="bg-white rounded-xl border border-slate-100 p-5">
        <h3 class="text-sm font-semibold text-slate-900 mb-3">{{ __('Link an existing Payment Voucher') }}</h3>
        @if ($linkableVouchers->isEmpty())
            <p class="text-sm text-slate-400">{{ __('No unlinked, issued payment vouchers found for this subcontractor.') }}</p>
        @else
            <form method="POST" action="{{ route('app.subcontracts.link-voucher', $subcontract) }}" class="flex flex-wrap items-center gap-2">
                @csrf
                <select name="payment_voucher_id" required class="rounded-lg border border-slate-200 text-sm min-w-[14rem]">
                    @foreach ($linkableVouchers as $v)
                        <option value="{{ $v->id }}">{{ $v->voucher_number }} — {{ $v->date->format('Y-m-d') }} — {{ number_format((float) $v->amount, 2) }}</option>
                    @endforeach
                </select>
                <select name="milestone_id" class="rounded-lg border border-slate-200 text-sm">
                    <option value="">{{ __('No milestone') }}</option>
                    @foreach ($subcontract->milestones as $m)<option value="{{ $m->id }}">{{ $m->name }}</option>@endforeach
                </select>
                <button type="submit" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:border-slate-300">{{ __('Link') }}</button>
            </form>
        @endif
        <p class="text-xs text-slate-400 mt-3">{{ __('Need to correct an amount or date on a linked voucher? Edit it directly from Cash & Banks > Payment Vouchers — this tracker follows it automatically.') }}</p>
    </div>

    <div class="bg-white rounded-xl border border-slate-100 p-5">
        <h3 class="text-sm font-semibold text-slate-900 mb-3">{{ __('Add a manual payment record') }}</h3>
        <p class="text-xs text-slate-400 mb-3">{{ __('For a payment made before this tracker existed, or outside the voucher system. Prefer linking a real Payment Voucher above whenever one exists.') }}</p>
        <form method="POST" action="{{ route('app.subcontracts.payments.store', $subcontract) }}" class="grid grid-cols-2 gap-2">
            @csrf
            <input type="date" name="paid_at" required value="{{ now()->toDateString() }}" class="rounded-lg border border-slate-200 text-sm">
            <input type="number" step="0.01" min="0.01" name="amount" required placeholder="{{ __('Amount') }}" class="rounded-lg border border-slate-200 text-sm">
            <select name="milestone_id" class="col-span-2 rounded-lg border border-slate-200 text-sm">
                <option value="">{{ __('No milestone') }}</option>
                @foreach ($subcontract->milestones as $m)<option value="{{ $m->id }}">{{ $m->name }}</option>@endforeach
            </select>
            <input type="text" name="reference" placeholder="{{ __('Reference') }}" class="col-span-2 rounded-lg border border-slate-200 text-sm">
            <button type="submit" class="col-span-2 rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:border-slate-300">{{ __('Add payment') }}</button>
        </form>
    </div>
</div>
@endsection
