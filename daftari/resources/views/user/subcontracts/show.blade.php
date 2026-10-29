@extends('layouts.app')

@section('title', $subcontract->supplier->name.' — '.$subcontract->project->name)

@section('content')
@php
    $totalPaid = $subcontract->totalPaid();
    $totalGross = $subcontract->totalReceivedGross();
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
        <p class="text-xs font-semibold uppercase text-slate-500">{{ __('Applied to contract (net)') }}</p>
        <p class="text-xl font-semibold text-teal-700 mt-1">{{ number_format($totalPaid, 2) }}</p>
        @if (abs($totalGross - $totalPaid) > 0.004)
            <p class="text-xs text-slate-400 mt-1">{{ __('Cash received (incl. VAT)') }}: {{ number_format($totalGross, 2) }}</p>
        @endif
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
            <th class="px-5 py-2.5 font-medium text-end">{{ __('Applied (net)') }}</th>
            <th class="px-5 py-2.5 font-medium text-end">{{ __('Cash (gross)') }}</th>
            <th class="px-5 py-2.5 font-medium"></th>
        </tr></thead>
        <tbody>
            @forelse ($subcontract->payments as $payment)
                <tr class="border-b border-slate-50 last:border-0">
                    <td class="px-5 py-2.5">{{ $payment->paid_at->format('Y-m-d') }}</td>
                    <td class="px-5 py-2.5">{{ $payment->milestone->name ?? __('Unassigned') }}</td>
                    <td class="px-5 py-2.5">
                        @if ($payment->payment_voucher_id)
                            <a href="{{ route('app.payment-vouchers.show', $payment->payment_voucher_id) }}" class="text-brand-700 hover:underline">{{ $payment->paymentVoucher->voucher_number }}</a>
                        @else
                            <span class="text-slate-400">{{ __('Manual entry') }}</span>
                        @endif
                    </td>
                    <td class="px-5 py-2.5">{{ $payment->reference ?: '—' }}</td>
                    <td class="px-5 py-2.5 text-end font-medium">{{ number_format((float) $payment->amount, 2) }}</td>
                    <td class="px-5 py-2.5 text-end text-slate-500">{{ $payment->gross_amount !== null && abs((float) $payment->gross_amount - (float) $payment->amount) > 0.004 ? number_format((float) $payment->gross_amount, 2) : '—' }}</td>
                    <td class="px-5 py-2.5 text-end">
                        <form method="POST" action="{{ route('app.subcontracts.payments.destroy', [$subcontract, $payment]) }}" onsubmit="return confirm('{{ __('Remove this payment from the tracker? The underlying voucher, if any, is not affected.') }}')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-xs text-red-500 hover:underline">{{ __('Remove') }}</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-5 py-8 text-center text-slate-400">{{ __('No payments recorded yet.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="bg-white rounded-xl border border-slate-100 mb-6 p-5" x-data="categoryBreakdownForm({{ json_encode(request()->filled('group_label') ? collect(request('group_label'))->map(fn ($l, $i) => ['label' => $l, 'terms' => request('group_terms')[$i] ?? '']) ->values() : collect($defaultCategoryGroups)->map(fn ($terms, $label) => ['label' => $label, 'terms' => $terms])->values()) }})">
    <div class="flex items-center justify-between mb-3">
        <h3 class="text-sm font-semibold text-slate-900">{{ __('Payment breakdown by category') }}</h3>
        <p class="text-xs text-slate-400">{{ __('Group this contract\'s own payments however you think about the cost — who got paid, what it was for.') }}</p>
    </div>
    <form method="GET" action="{{ route('app.subcontracts.show', $subcontract) }}">
        <template x-for="(row, i) in rows" :key="i">
            <div class="grid grid-cols-12 gap-2 mb-2 items-center">
                <input type="text" :name="`group_label[${i}]`" x-model="row.label" placeholder="{{ __('Category name, e.g. Sub Base') }}" class="col-span-4 rounded-lg border border-slate-200 text-sm">
                <input type="text" :name="`group_terms[${i}]`" x-model="row.terms" placeholder="{{ __('Match keyword(s), comma-separated, e.g. subbase, trailer') }}" class="col-span-7 rounded-lg border border-slate-200 text-sm">
                <button type="button" @click="removeRow(i)" class="col-span-1 text-red-500 text-xs">&times;</button>
            </div>
        </template>
        <div class="flex items-center gap-3 mt-2">
            <button type="button" @click="addRow()" class="text-xs font-semibold text-brand-700 hover:underline">+ {{ __('Add category') }}</button>
            <button type="submit" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:border-slate-300">{{ __('Show breakdown') }}</button>
            @if ($categoryGroups)
                <a href="{{ route('app.subcontracts.category-report.pdf', array_merge(['subcontract' => $subcontract->id], request()->only('group_label', 'group_terms'))) }}" class="text-xs font-semibold text-brand-700 hover:underline">{{ __('Download PDF') }}</a>
            @endif
        </div>
    </form>

    @if ($categoryGroups)
        <table class="w-full text-sm mt-4 border-t border-slate-100 pt-2">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="py-2 font-medium">{{ __('Category') }}</th>
                    <th class="py-2 font-medium text-end">{{ __('Items') }}</th>
                    <th class="py-2 font-medium text-end">{{ __('Total') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($categoryGroups as $group)
                    <tr class="border-b border-slate-50 last:border-0">
                        <td class="py-2 font-medium text-slate-800">{{ $group['label'] }}</td>
                        <td class="py-2 text-end text-slate-500">{{ $group['payments']->count() }}</td>
                        <td class="py-2 text-end font-semibold">{{ number_format($group['total'], 2) }}</td>
                    </tr>
                @endforeach
                <tr class="font-semibold">
                    <td class="py-2" colspan="2">{{ __('Total') }}</td>
                    <td class="py-2 text-end">{{ number_format(collect($categoryGroups)->sum('total'), 2) }}</td>
                </tr>
            </tbody>
        </table>
    @endif
</div>

<script>
function categoryBreakdownForm(initialRows) {
    return {
        rows: initialRows.length ? initialRows : [{ label: '', terms: '' }],
        addRow() { this.rows.push({ label: '', terms: '' }); },
        removeRow(i) { this.rows.splice(i, 1); },
    };
}
</script>

<div class="bg-white rounded-xl border border-slate-100 mb-6 p-5">
    <div class="flex items-center justify-between mb-1">
        <h3 class="text-sm font-semibold text-slate-900">{{ __('Receiving Payments Report categories') }}</h3>
    </div>
    <p class="text-xs text-slate-400 mb-3">{{ __('Controls how the printed Receiving Payments Report lists this contract\'s payments. An "Itemized" category still lists every matching real payment (like Shahid\'s cash); a "Measured" category collapses to one quantity × rate line, like a site measurement sheet (Sub Base, Asphalt). Leave empty to keep printing the plain milestone-by-milestone ledger.') }}</p>

    @if ($subcontract->reportCategories->isNotEmpty())
        <table class="w-full text-sm mb-4">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="py-2 font-medium">{{ __('Label') }}</th>
                    <th class="py-2 font-medium">{{ __('Mode') }}</th>
                    <th class="py-2 font-medium">{{ __('Match keywords') }}</th>
                    <th class="py-2 font-medium text-end">{{ __('Quantity') }}</th>
                    <th class="py-2 font-medium">{{ __('Unit') }}</th>
                    <th class="py-2 font-medium text-end">{{ __('Rate') }}</th>
                    <th class="py-2 font-medium text-end">{{ __('Amount') }}</th>
                    <th class="py-2 font-medium"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($subcontract->reportCategories as $category)
                    <tr class="border-b border-slate-50 last:border-0" x-data="{ mode: '{{ $category->display_mode }}' }">
                        <form method="POST" action="{{ route('app.subcontracts.report-categories.update', [$subcontract, $category]) }}" class="contents">
                            @csrf @method('PUT')
                            <td class="py-2"><input type="text" name="label" value="{{ $category->label }}" required class="w-full rounded-lg border border-slate-200 text-sm"></td>
                            <td class="py-2">
                                <select name="display_mode" x-model="mode" class="rounded-lg border border-slate-200 text-sm">
                                    <option value="measured" @selected($category->display_mode === 'measured')>{{ __('Measured') }}</option>
                                    <option value="itemized" @selected($category->display_mode === 'itemized')>{{ __('Itemized') }}</option>
                                </select>
                            </td>
                            <td class="py-2"><input type="text" name="match_terms" value="{{ $category->match_terms }}" placeholder="{{ __('e.g. shahid, mc1') }}" class="w-full rounded-lg border border-slate-200 text-sm"></td>
                            <td class="py-2" x-show="mode === 'measured'"><input type="number" step="0.001" name="quantity" value="{{ $category->quantity }}" class="w-20 rounded-lg border border-slate-200 text-sm text-end"></td>
                            <td class="py-2" x-show="mode === 'measured'"><input type="text" name="unit" value="{{ $category->unit }}" placeholder="{{ __('trip, ton…') }}" class="w-20 rounded-lg border border-slate-200 text-sm"></td>
                            <td class="py-2" x-show="mode === 'measured'"><input type="number" step="0.01" name="rate" value="{{ $category->rate }}" class="w-20 rounded-lg border border-slate-200 text-sm text-end"></td>
                            <td class="py-2" x-show="mode === 'measured'"><input type="number" step="0.01" name="amount" value="{{ $category->amount }}" placeholder="{{ __('flat amount') }}" class="w-24 rounded-lg border border-slate-200 text-sm text-end"></td>
                            <td class="py-2 text-end" x-show="mode === 'itemized'" colspan="4">
                                @php $liveRow = collect($reportCategoryRows ?? [])->first(fn ($r) => $r['category']->id === $category->id); @endphp
                                <span class="text-slate-400 text-xs">{{ __('Live total') }}: {{ number_format($liveRow['total'] ?? 0, 2) }}</span>
                            </td>
                            <td class="py-2 text-end"><button type="submit" class="text-xs font-semibold text-brand-700 hover:underline">{{ __('Save') }}</button></td>
                        </form>
                        <form method="POST" action="{{ route('app.subcontracts.report-categories.destroy', [$subcontract, $category]) }}" onsubmit="return confirm('{{ __('Remove this report category?') }}')" class="contents">
                            @csrf @method('DELETE')
                            <td class="py-2 text-end"><button type="submit" class="text-xs text-red-500 hover:underline">{{ __('Remove') }}</button></td>
                        </form>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <form method="POST" action="{{ route('app.subcontracts.report-categories.store', $subcontract) }}" class="grid grid-cols-12 gap-2 items-end" x-data="{ mode: 'measured' }">
        @csrf
        <div class="col-span-3">
            <label class="block text-[11px] text-slate-400">{{ __('Label') }}</label>
            <input type="text" name="label" required placeholder="{{ __('e.g. Sub Base') }}" class="w-full rounded-lg border border-slate-200 text-sm">
        </div>
        <div class="col-span-2">
            <label class="block text-[11px] text-slate-400">{{ __('Mode') }}</label>
            <select name="display_mode" x-model="mode" class="w-full rounded-lg border border-slate-200 text-sm">
                <option value="measured">{{ __('Measured') }}</option>
                <option value="itemized">{{ __('Itemized') }}</option>
            </select>
        </div>
        <div class="col-span-3">
            <label class="block text-[11px] text-slate-400">{{ __('Match keywords') }}</label>
            <input type="text" name="match_terms" placeholder="{{ __('comma-separated') }}" class="w-full rounded-lg border border-slate-200 text-sm">
        </div>
        <div class="col-span-1" x-show="mode === 'measured'">
            <label class="block text-[11px] text-slate-400">{{ __('Qty') }}</label>
            <input type="number" step="0.001" name="quantity" class="w-full rounded-lg border border-slate-200 text-sm">
        </div>
        <div class="col-span-1" x-show="mode === 'measured'">
            <label class="block text-[11px] text-slate-400">{{ __('Unit') }}</label>
            <input type="text" name="unit" class="w-full rounded-lg border border-slate-200 text-sm">
        </div>
        <div class="col-span-1" x-show="mode === 'measured'">
            <label class="block text-[11px] text-slate-400">{{ __('Rate') }}</label>
            <input type="number" step="0.01" name="rate" class="w-full rounded-lg border border-slate-200 text-sm">
        </div>
        <div class="col-span-1" x-show="mode === 'measured'">
            <label class="block text-[11px] text-slate-400">{{ __('Amount') }}</label>
            <input type="number" step="0.01" name="amount" placeholder="{{ __('flat') }}" class="w-full rounded-lg border border-slate-200 text-sm">
        </div>
        <div class="col-span-12">
            <button type="submit" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:border-slate-300">{{ __('Add category') }}</button>
        </div>
    </form>
</div>

<div class="grid sm:grid-cols-2 gap-6">
    <div class="bg-white rounded-xl border border-slate-100 p-5">
        <h3 class="text-sm font-semibold text-slate-900 mb-3">{{ __('Link an existing Payment Voucher') }}</h3>
        @if ($linkableVouchers->isEmpty())
            <p class="text-sm text-slate-400">{{ __('No unlinked, issued payment vouchers found for this subcontractor.') }}</p>
        @else
            <form method="POST" action="{{ route('app.subcontracts.link-voucher', $subcontract) }}" class="grid grid-cols-2 gap-2">
                @csrf
                <select name="payment_voucher_id" required class="col-span-2 rounded-lg border border-slate-200 text-sm">
                    @foreach ($linkableVouchers as $v)
                        <option value="{{ $v->id }}">{{ $v->voucher_number }} — {{ $v->date->format('Y-m-d') }} — {{ number_format((float) $v->amount, 2) }}</option>
                    @endforeach
                </select>
                <input type="number" step="0.01" min="0.01" name="net_amount" placeholder="{{ __('Amount to apply, if different (ex. VAT)') }}" class="col-span-2 rounded-lg border border-slate-200 text-sm">
                <select name="milestone_id" class="col-span-2 rounded-lg border border-slate-200 text-sm">
                    <option value="">{{ __('Auto-fill milestones in order') }}</option>
                    @foreach ($subcontract->milestones as $m)<option value="{{ $m->id }}">{{ $m->name }}</option>@endforeach
                </select>
                <button type="submit" class="col-span-2 rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:border-slate-300">{{ __('Link') }}</button>
            </form>
        @endif
        <p class="text-xs text-slate-400 mt-3">{{ __('Leave the applied amount blank to apply the full voucher amount. Leave milestone blank to fill whichever milestone is next due, spilling any remainder into the one after it. Need to correct the real voucher amount or date itself? Edit it from Cash & Banks > Payment Vouchers.') }}</p>
    </div>

    <div class="bg-white rounded-xl border border-slate-100 p-5">
        <h3 class="text-sm font-semibold text-slate-900 mb-3">{{ __('Add a manual payment record') }}</h3>
        <p class="text-xs text-slate-400 mb-3">{{ __('For a payment made before this tracker existed, or outside the voucher system. Prefer linking a real Payment Voucher above whenever one exists.') }}</p>
        <form method="POST" action="{{ route('app.subcontracts.payments.store', $subcontract) }}" class="grid grid-cols-2 gap-2">
            @csrf
            <input type="date" name="paid_at" required value="{{ now()->toDateString() }}" class="rounded-lg border border-slate-200 text-sm">
            <input type="number" step="0.01" min="0.01" name="amount" required placeholder="{{ __('Amount to apply (ex. VAT)') }}" class="rounded-lg border border-slate-200 text-sm">
            <input type="number" step="0.01" min="0.01" name="gross_amount" placeholder="{{ __('Cash paid, if different (incl. VAT)') }}" class="col-span-2 rounded-lg border border-slate-200 text-sm">
            <select name="milestone_id" class="col-span-2 rounded-lg border border-slate-200 text-sm">
                <option value="">{{ __('Auto-fill milestones in order') }}</option>
                @foreach ($subcontract->milestones as $m)<option value="{{ $m->id }}">{{ $m->name }}</option>@endforeach
            </select>
            <input type="text" name="reference" placeholder="{{ __('Reference') }}" class="col-span-2 rounded-lg border border-slate-200 text-sm">
            <button type="submit" class="col-span-2 rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:border-slate-300">{{ __('Add payment') }}</button>
        </form>
    </div>
</div>
@endsection
