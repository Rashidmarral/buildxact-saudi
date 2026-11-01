@extends('layouts.app')

@section('title', $project->name)

@php
    $revenue = $project->revenue();
    $costs = $project->costs();
    $margin = $project->margin();
    $marginPct = $project->marginPercent();
    $typeLabels = ['internal' => __('Internal'), 'service_tm' => __('Service / time & material'), 'construction_contract' => __('Construction contract'), 'real_estate_development' => __('Real estate development'), 'maintenance_amc' => __('Maintenance (AMC)')];
    $billingTypeLabels = ['no_billing' => __('No billing'), 'fixed_price' => __('Fixed price'), 'time_and_material' => __('Time & material'), 'milestones' => __('Milestones'), 'progress_retention' => __('Progress (with retention)'), 'recurring_amc' => __('Recurring (AMC)'), 'per_unit_sale' => __('Per-unit sale')];
@endphp

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <div class="flex items-center gap-3">
            <h2 class="text-lg font-semibold text-slate-900">{{ $project->name }}</h2>
            <span class="text-xs font-mono text-slate-400">{{ $project->code }}</span>
            <span class="inline-block rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600">{{ $typeLabels[$project->type] ?? $project->type }}</span>
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

<div class="flex flex-wrap items-center gap-2 mb-6 text-sm" id="project-tabs">
    <button type="button" data-tab="overview" class="project-tab rounded-lg px-3 py-1.5 bg-slate-900 text-white">{{ __('Overview') }}</button>
    <button type="button" data-tab="documents" class="project-tab rounded-lg px-3 py-1.5 text-slate-600 hover:bg-slate-100">{{ __('Documents') }} ({{ $project->attachments->count() }})</button>
</div>

<div class="project-panel" data-panel="overview">
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

    <div class="bg-white rounded-xl border border-slate-100 p-6 mb-6">
        <h3 class="font-semibold text-slate-900 mb-4">{{ __('Project details') }}</h3>
        <dl class="grid sm:grid-cols-3 gap-4 text-sm">
            <div><dt class="text-xs text-slate-400">{{ __('Status') }}</dt><dd class="text-slate-800 mt-0.5">{{ ucfirst(str_replace('_', ' ', $project->status)) }}</dd></div>
            <div><dt class="text-xs text-slate-400">{{ __('Project manager') }}</dt><dd class="text-slate-800 mt-0.5">{{ $project->projectManager?->full_name ?? '—' }}</dd></div>
            <div><dt class="text-xs text-slate-400">{{ __('Business location') }}</dt><dd class="text-slate-800 mt-0.5">{{ $project->businessLocation?->name ?? '—' }}</dd></div>
            <div><dt class="text-xs text-slate-400">{{ __('Cost centre') }}</dt><dd class="text-slate-800 mt-0.5">{{ $project->costCenter?->name ?? '—' }}</dd></div>
            <div><dt class="text-xs text-slate-400">{{ __('Site address') }}</dt><dd class="text-slate-800 mt-0.5">{{ $project->site_address ?: '—' }}{{ $project->city ? ', '.$project->city : '' }}</dd></div>
            <div><dt class="text-xs text-slate-400">{{ __('Dates') }}</dt><dd class="text-slate-800 mt-0.5">{{ optional($project->start_date)->format('Y-m-d') ?? '—' }} &rarr; {{ optional($project->end_date)->format('Y-m-d') ?? '—' }}</dd></div>
        </dl>
        @if ($project->description)
            <p class="text-sm text-slate-600 mt-4 border-t border-slate-100 pt-4">{{ $project->description }}</p>
        @endif
    </div>

    <div class="bg-white rounded-xl border border-slate-100 p-6 mb-6">
        <h3 class="font-semibold text-slate-900 mb-4">{{ __('Billing & contract') }}</h3>
        <dl class="grid sm:grid-cols-4 gap-4 text-sm">
            <div><dt class="text-xs text-slate-400">{{ __('Billing type') }}</dt><dd class="text-slate-800 mt-0.5">{{ $billingTypeLabels[$project->billing_type] ?? $project->billing_type }}</dd></div>
            <div><dt class="text-xs text-slate-400">{{ __('Contract value') }}</dt><dd class="text-slate-800 mt-0.5">{{ $project->contract_value ? \App\Support\Money::format($project->contract_value) : '—' }}</dd></div>
            <div><dt class="text-xs text-slate-400">{{ __('Retention') }}</dt><dd class="text-slate-800 mt-0.5">{{ $project->retention_percent ? $project->retention_percent.'% ('.($project->retention_calculated_on === 'including_vat' ? __('incl. VAT') : __('excl. VAT')).')' : '—' }}</dd></div>
            <div><dt class="text-xs text-slate-400">{{ __('Advance recovery') }}</dt><dd class="text-slate-800 mt-0.5">{{ $project->advance_recovery_percent ? $project->advance_recovery_percent.'%' : '—' }}</dd></div>
        </dl>
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
</div>

<div class="project-panel hidden" data-panel="documents">
    <div class="bg-white rounded-xl border border-slate-100 p-6 mb-6">
        <h3 class="font-semibold text-slate-900 mb-4">{{ __('Attach a document') }}</h3>
        <form method="POST" action="{{ route('app.projects.attachments.store', $project) }}" enctype="multipart/form-data" class="flex flex-wrap items-end gap-3">
            @csrf
            <input type="file" name="file" required class="text-sm">
            <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Upload') }}</button>
        </form>
        @error('file')<p class="text-sm text-red-600 mt-2">{{ $message }}</p>@enderror
    </div>

    <div class="bg-white rounded-xl border border-slate-100">
        @if ($project->attachments->isEmpty())
            <p class="px-6 py-8 text-sm text-slate-500">{{ __('No documents attached yet.') }}</p>
        @else
            <table class="w-full text-sm">
                <tbody>
                    @foreach ($project->attachments as $attachment)
                        <tr class="border-b border-slate-50 last:border-0">
                            <td class="px-6 py-3"><a href="{{ \Illuminate\Support\Facades\Storage::url($attachment->path) }}" target="_blank" class="text-brand-700 hover:underline">{{ $attachment->original_name }}</a></td>
                            <td class="px-6 py-3 text-slate-400">{{ $attachment->humanSize() }}</td>
                            <td class="px-6 py-3 text-slate-400">{{ $attachment->uploader?->name ?? '—' }}</td>
                            <td class="px-6 py-3 text-right">
                                <form method="POST" action="{{ route('app.projects.attachments.destroy', [$project, $attachment]) }}" onsubmit="return confirm('{{ __('Remove this attachment?') }}')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-xs font-semibold text-red-600 hover:underline">{{ __('Remove') }}</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>

<script>
document.querySelectorAll('.project-tab').forEach(btn => btn.addEventListener('click', () => {
    document.querySelectorAll('.project-tab').forEach(b => b.classList.remove('bg-slate-900', 'text-white'));
    document.querySelectorAll('.project-tab').forEach(b => b.classList.add('text-slate-600'));
    btn.classList.add('bg-slate-900', 'text-white');
    btn.classList.remove('text-slate-600');
    document.querySelectorAll('.project-panel').forEach(p => p.classList.toggle('hidden', p.dataset.panel !== btn.dataset.tab));
}));
</script>
@endsection
