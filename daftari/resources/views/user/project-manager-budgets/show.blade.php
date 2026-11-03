@extends('layouts.app')

@section('title', $subcontract->partyName().' — '.$subcontract->project->name)

@section('content')
@php
    $totalPaid = $subcontract->totalPaid();
    $totalGross = $subcontract->totalReceivedGross();
    $balance = $subcontract->balanceBeforeRetention();
@endphp

<div class="flex items-start justify-between mb-6">
    <div>
        <h2 class="text-lg font-semibold text-slate-900">{{ $subcontract->title ?: __('Project Manager Budget') }}</h2>
        <p class="text-sm text-slate-500 mt-1">{{ __('Project manager') }}: {{ $subcontract->partyName() }} — {{ $subcontract->project->name }} @if($subcontract->contract_number) · {{ $subcontract->contract_number }} @endif</p>
    </div>
    <div class="flex items-center gap-2">
        <a href="{{ route('app.project-manager-budgets.report.pdf', $subcontract) }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Budget Report (PDF)') }}</a>
        <a href="{{ route('app.project-manager-budgets.edit', $subcontract) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Edit') }}</a>
    </div>
</div>

@if (session('status'))
    <div class="mb-4 rounded-lg border border-teal-200 bg-teal-50 px-4 py-3 text-sm text-teal-700">{{ session('status') }}</div>
@endif

<div class="grid sm:grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-slate-100 p-5">
        <p class="text-xs font-semibold uppercase text-slate-500">{{ __('Total budget') }}</p>
        <p class="text-xl font-semibold text-slate-900 mt-1">{{ number_format((float) $subcontract->contract_value, 2) }}</p>
    </div>
    <div class="bg-white rounded-xl border border-slate-100 p-5">
        <p class="text-xs font-semibold uppercase text-slate-500">{{ __('Advanced to date') }}</p>
        <p class="text-xl font-semibold text-teal-700 mt-1">{{ number_format($totalPaid, 2) }}</p>
        @if (abs($totalGross - $totalPaid) > 0.004)
            <p class="text-xs text-slate-400 mt-1">{{ __('Cash given (incl. VAT)') }}: {{ number_format($totalGross, 2) }}</p>
        @endif
    </div>
    <div class="bg-white rounded-xl border border-slate-100 p-5">
        <p class="text-xs font-semibold uppercase text-slate-500">{{ __('Balance remaining') }}</p>
        <p class="text-xl font-semibold {{ $balance > 0 ? 'text-amber-600' : 'text-slate-900' }} mt-1">{{ number_format($balance, 2) }}</p>
    </div>
</div>

@if ($subcontract->boqItems->isNotEmpty())
    <div class="bg-white rounded-xl border border-slate-100 mb-6">
        <div class="px-5 py-3 border-b border-slate-100"><h3 class="text-sm font-semibold text-slate-900">{{ __('Budget breakdown') }}</h3></div>
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

<div class="bg-white rounded-xl border border-slate-100 mb-6" x-data="{ selected: [], allIds: {{ Illuminate\Support\Js::from($subcontract->payments->pluck('id')->map('strval')) }} }">
    <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between">
        <h3 class="text-sm font-semibold text-slate-900">{{ __('Payments') }}</h3>
        <form method="POST" action="{{ route('app.project-manager-budgets.payments.destroy-many', $subcontract) }}" onsubmit="return confirm('{{ __('Remove the selected payments from the tracker? The underlying vouchers, if any, are not affected.') }}')" x-show="selected.length" x-cloak>
            @csrf @method('DELETE')
            <template x-for="id in selected" :key="id"><input type="hidden" name="payment_id[]" :value="id"></template>
            <button type="submit" class="rounded-lg bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-100">
                {{ __('Remove selected') }} (<span x-text="selected.length"></span>)
            </button>
        </form>
    </div>
    <table class="w-full text-sm">
        <thead><tr class="text-left text-slate-500 border-b border-slate-100">
            <th class="px-5 py-2.5 w-8">
                <input type="checkbox" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500" :checked="selected.length && selected.length === allIds.length" @change="selected = $event.target.checked ? [...allIds] : []">
            </th>
            <th class="px-5 py-2.5 font-medium">{{ __('Date') }}</th>
            @if ($subcontract->works->isNotEmpty())
                <th class="px-5 py-2.5 font-medium">{{ __('Work') }}</th>
            @endif
            <th class="px-5 py-2.5 font-medium">{{ __('Source') }}</th>
            <th class="px-5 py-2.5 font-medium">{{ __('Reference') }}</th>
            <th class="px-5 py-2.5 font-medium text-end">{{ __('Applied (net)') }}</th>
            <th class="px-5 py-2.5 font-medium text-end">{{ __('Cash (gross)') }}</th>
        </tr></thead>
        <tbody>
            @forelse ($subcontract->payments as $payment)
                <tr class="border-b border-slate-50 last:border-0" :class="selected.includes('{{ $payment->id }}') ? 'bg-brand-50/40' : ''">
                    <td class="px-5 py-2.5"><input type="checkbox" value="{{ $payment->id }}" x-model="selected" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500"></td>
                    <td class="px-5 py-2.5">{{ $payment->paid_at->format('Y-m-d') }}</td>
                    @if ($subcontract->works->isNotEmpty())
                        <td class="px-5 py-2.5">
                            <form method="POST" action="{{ route('app.project-manager-budgets.payments.update', [$subcontract, $payment]) }}">
                                @csrf @method('PUT')
                                <input type="hidden" name="amount" value="{{ $payment->amount }}">
                                <input type="hidden" name="paid_at" value="{{ $payment->paid_at->toDateString() }}">
                                @if ($payment->gross_amount !== null)<input type="hidden" name="gross_amount" value="{{ $payment->gross_amount }}">@endif
                                @if ($payment->reference)<input type="hidden" name="reference" value="{{ $payment->reference }}">@endif
                                @if ($payment->method)<input type="hidden" name="method" value="{{ $payment->method }}">@endif
                                @if ($payment->milestone_id)<input type="hidden" name="milestone_id" value="{{ $payment->milestone_id }}">@endif
                                <select name="work_id" onchange="this.form.submit()" class="rounded-lg border border-slate-200 text-xs">
                                    <option value="">{{ __('Unassigned') }}</option>
                                    @foreach ($subcontract->works as $w)<option value="{{ $w->id }}" @selected($payment->work_id === $w->id)>{{ $w->name }}</option>@endforeach
                                </select>
                            </form>
                        </td>
                    @endif
                    <td class="px-5 py-2.5">
                        @if ($payment->payment_voucher_id && $payment->paymentVoucher)
                            <a href="{{ route('app.payment-vouchers.show', $payment->payment_voucher_id) }}" class="text-brand-700 hover:underline">{{ $payment->paymentVoucher->voucher_number }}</a>
                        @elseif ($payment->payment_voucher_id)
                            <span class="text-slate-400" title="{{ __('The linked payment voucher was deleted.') }}">{{ __('Deleted voucher') }}</span>
                        @else
                            <span class="text-slate-400">{{ __('Manual entry') }}</span>
                        @endif
                    </td>
                    <td class="px-5 py-2.5">{{ $payment->reference ?: '—' }}</td>
                    <td class="px-5 py-2.5 text-end font-medium">{{ number_format((float) $payment->amount, 2) }}</td>
                    <td class="px-5 py-2.5 text-end text-slate-500">{{ $payment->gross_amount !== null && abs((float) $payment->gross_amount - (float) $payment->amount) > 0.004 ? number_format((float) $payment->gross_amount, 2) : '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-5 py-8 text-center text-slate-400">{{ __('No payments recorded yet.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="bg-white rounded-xl border border-slate-100 mb-6 p-5" x-data="categoryBreakdownForm({{ Illuminate\Support\Js::from(request()->filled('group_label') ? collect(request('group_label'))->map(fn ($l, $i) => ['label' => $l, 'terms' => request('group_terms')[$i] ?? '']) ->values() : collect($defaultCategoryGroups)->map(fn ($terms, $label) => ['label' => $label, 'terms' => $terms])->values()) }})">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
        <h3 class="text-sm font-semibold text-slate-900">{{ __('Spending breakdown by category') }}</h3>
        <p class="text-xs text-slate-400">{{ __('Group this budget\'s own payments however you think about the cost — who it went to, what it was for.') }}</p>
    </div>
    <form method="GET" action="{{ route('app.project-manager-budgets.show', $subcontract) }}">
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
                <a href="{{ route('app.project-manager-budgets.category-report.pdf', array_merge(['subcontract' => $subcontract->id], request()->only('group_label', 'group_terms'))) }}" class="text-xs font-semibold text-brand-700 hover:underline">{{ __('Download PDF') }}</a>
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
    <div class="flex flex-wrap items-center justify-between gap-3 mb-1">
        <h3 class="text-sm font-semibold text-slate-900">{{ __('Budget Report categories') }}</h3>
    </div>
    <p class="text-xs text-slate-400 mb-3">{{ __('Controls how the printed Budget Report lists this project manager\'s spending. An "Itemized" category still lists every matching real payment; a "Measured" category collapses to one quantity × rate line, like a site measurement sheet (Sub Base, Asphalt). Leave empty to keep printing the plain payment ledger.') }}</p>

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
                        <form method="POST" action="{{ route('app.project-manager-budgets.report-categories.update', [$subcontract, $category]) }}" class="contents">
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
                        <form method="POST" action="{{ route('app.project-manager-budgets.report-categories.destroy', [$subcontract, $category]) }}" onsubmit="return confirm('{{ __('Remove this report category?') }}')" class="contents">
                            @csrf @method('DELETE')
                            <td class="py-2 text-end"><button type="submit" class="text-xs text-red-500 hover:underline">{{ __('Remove') }}</button></td>
                        </form>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <form method="POST" action="{{ route('app.project-manager-budgets.report-categories.store', $subcontract) }}" class="grid grid-cols-12 gap-2 items-end" x-data="{ mode: 'measured' }">
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
        <h3 class="text-sm font-semibold text-slate-900 mb-3">{{ __('Link existing Payment Vouchers') }}</h3>
        @if ($linkableVouchers->isEmpty())
            <p class="text-sm text-slate-400">{{ __('No unlinked, issued payment vouchers found for this project manager.') }}</p>
        @else
            <form method="POST" action="{{ route('app.project-manager-budgets.link-voucher', $subcontract) }}" x-data="{ selected: [] }">
                @csrf
                <div class="flex items-center justify-between mb-2">
                    <label class="inline-flex items-center gap-2 text-xs text-slate-500 cursor-pointer">
                        <input type="checkbox" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500" :checked="selected.length && selected.length === {{ $linkableVouchers->count() }}" @change="selected = $event.target.checked ? {{ Illuminate\Support\Js::from($linkableVouchers->pluck('id')->map(fn ($id) => (string) $id)) }} : []">
                        {{ __('Select all') }}
                    </label>
                    <span class="text-xs text-slate-400" x-show="selected.length" x-cloak><span x-text="selected.length"></span> {{ __('selected') }}</span>
                </div>
                <div class="rounded-lg border border-slate-200 divide-y divide-slate-100 max-h-56 overflow-y-auto mb-3">
                    @foreach ($linkableVouchers as $v)
                        <label class="flex items-center justify-between gap-3 px-3 py-2 text-sm hover:bg-slate-50 cursor-pointer" :class="selected.includes('{{ $v->id }}') ? 'bg-brand-50/60' : ''">
                            <span class="flex items-center gap-2 min-w-0">
                                <input type="checkbox" name="payment_voucher_id[]" value="{{ $v->id }}" x-model="selected" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                                <span class="font-medium text-slate-700 truncate">{{ $v->voucher_number }}</span>
                                <span class="text-slate-400 shrink-0">{{ $v->date->format('Y-m-d') }}</span>
                            </span>
                            <span class="tabular-nums text-slate-600 shrink-0">{{ number_format((float) $v->amount, 2) }}</span>
                        </label>
                    @endforeach
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <input type="number" step="0.01" min="0.01" name="net_amount" :disabled="selected.length !== 1" :class="selected.length !== 1 ? 'opacity-50' : ''" placeholder="{{ __('Amount to apply, if different (ex. VAT) — only with one selected') }}" class="col-span-2 rounded-lg border border-slate-200 text-sm">
                    @if ($subcontract->works->isNotEmpty())
                        <select name="work_id" class="col-span-2 rounded-lg border border-slate-200 text-sm">
                            <option value="">{{ __('No specific work') }}</option>
                            @foreach ($subcontract->works as $w)<option value="{{ $w->id }}">{{ $w->name }}</option>@endforeach
                        </select>
                    @endif
                    <button type="submit" :disabled="!selected.length" class="col-span-2 rounded-lg bg-brand-600 px-3 py-2 text-sm font-semibold text-white hover:bg-brand-700 disabled:bg-slate-100 disabled:text-slate-400">
                        {{ __('Link') }} <template x-if="selected.length > 1">(<span x-text="selected.length"></span>)</template>
                    </button>
                </div>
            </form>
        @endif
        <p class="text-xs text-slate-400 mt-3">{{ __('Leave the applied amount blank to apply the full voucher amount — it only applies when exactly one voucher is selected. The voucher must be paid to this same employee (party_type = Employee) — create it from Cash & Banks > Payment Vouchers if it doesn\'t exist yet.') }}</p>
    </div>

    <div class="bg-white rounded-xl border border-slate-100 p-5">
        <h3 class="text-sm font-semibold text-slate-900 mb-3">{{ __('Add a manual payment record') }}</h3>
        <p class="text-xs text-slate-400 mb-3">{{ __('For cash given before this tracker existed, or outside the voucher system. Prefer linking a real Payment Voucher above whenever one exists.') }}</p>
        <form method="POST" action="{{ route('app.project-manager-budgets.payments.store', $subcontract) }}" class="grid grid-cols-2 gap-2">
            @csrf
            <input type="date" name="paid_at" required value="{{ now()->toDateString() }}" class="rounded-lg border border-slate-200 text-sm">
            <input type="number" step="0.01" min="0.01" name="amount" required placeholder="{{ __('Amount to apply (ex. VAT)') }}" class="rounded-lg border border-slate-200 text-sm">
            <input type="number" step="0.01" min="0.01" name="gross_amount" placeholder="{{ __('Cash given, if different (incl. VAT)') }}" class="col-span-2 rounded-lg border border-slate-200 text-sm">
            @if ($subcontract->works->isNotEmpty())
                <select name="work_id" class="col-span-2 rounded-lg border border-slate-200 text-sm">
                    <option value="">{{ __('No specific work') }}</option>
                    @foreach ($subcontract->works as $w)<option value="{{ $w->id }}">{{ $w->name }}</option>@endforeach
                </select>
            @endif
            <input type="text" name="reference" placeholder="{{ __('Reference') }}" class="col-span-2 rounded-lg border border-slate-200 text-sm">
            <button type="submit" class="col-span-2 rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:border-slate-300">{{ __('Add payment') }}</button>
        </form>
    </div>
</div>

<div class="bg-white rounded-xl border border-slate-100 mb-6 p-5">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-1">
        <h3 class="text-sm font-semibold text-slate-900">{{ __('Works / Sub-projects') }}</h3>
    </div>
    <p class="text-xs text-slate-400 mb-3">{{ __('If this project manager is actually running several distinct pieces of work (e.g. "MC1", "RC2 Spraying"), name each one here, then assign payments to the right one above — leave this empty for a simple, single-work budget.') }}</p>

    @if ($subcontract->works->isNotEmpty())
        <table class="w-full text-sm mb-4">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="py-2 font-medium">{{ __('Name') }}</th>
                    <th class="py-2 font-medium text-end">{{ __('Budget (optional)') }}</th>
                    <th class="py-2 font-medium text-end">{{ __('Paid') }}</th>
                    <th class="py-2 font-medium text-end">{{ __('Balance') }}</th>
                    <th class="py-2 font-medium"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($subcontract->works as $work)
                    <tr class="border-b border-slate-50 last:border-0">
                        <form method="POST" action="{{ route('app.project-manager-budgets.works.update', [$subcontract, $work]) }}" class="contents">
                            @csrf @method('PUT')
                            <td class="py-2"><input type="text" name="name" value="{{ $work->name }}" required class="w-full rounded-lg border border-slate-200 text-sm"></td>
                            <td class="py-2"><input type="number" step="0.01" min="0" name="budget_amount" value="{{ $work->budget_amount }}" placeholder="{{ __('optional') }}" class="w-28 rounded-lg border border-slate-200 text-sm text-end"></td>
                            <td class="py-2 text-end text-teal-700">{{ number_format($work->paidAmount(), 2) }}</td>
                            <td class="py-2 text-end font-medium">{{ $work->balance() !== null ? number_format($work->balance(), 2) : '—' }}</td>
                            <td class="py-2 text-end"><button type="submit" class="text-xs font-semibold text-brand-700 hover:underline">{{ __('Save') }}</button></td>
                        </form>
                        <form method="POST" action="{{ route('app.project-manager-budgets.works.destroy', [$subcontract, $work]) }}" onsubmit="return confirm('{{ __('Remove this work? Any payments tagged to it keep their own record, just untagged.') }}')" class="contents">
                            @csrf @method('DELETE')
                            <td class="py-2 text-end"><button type="submit" class="text-xs text-red-500 hover:underline">{{ __('Remove') }}</button></td>
                        </form>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <form method="POST" action="{{ route('app.project-manager-budgets.works.store', $subcontract) }}" class="grid grid-cols-12 gap-2 items-end">
        @csrf
        <div class="col-span-7">
            <label class="block text-[11px] text-slate-400">{{ __('Name') }}</label>
            <input type="text" name="name" required placeholder="{{ __('e.g. MC1, RC2 Spraying') }}" class="w-full rounded-lg border border-slate-200 text-sm">
        </div>
        <div class="col-span-3">
            <label class="block text-[11px] text-slate-400">{{ __('Budget (optional)') }}</label>
            <input type="number" step="0.01" min="0" name="budget_amount" class="w-full rounded-lg border border-slate-200 text-sm">
        </div>
        <div class="col-span-2">
            <button type="submit" class="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:border-slate-300">{{ __('Add work') }}</button>
        </div>
    </form>
</div>
@endsection
