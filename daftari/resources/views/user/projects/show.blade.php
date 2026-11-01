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
    <button type="button" data-tab="tasks" class="project-tab rounded-lg px-3 py-1.5 text-slate-600 hover:bg-slate-100">{{ __('Tasks') }} ({{ $project->tasks->count() }})</button>
    <button type="button" data-tab="boq" class="project-tab rounded-lg px-3 py-1.5 text-slate-600 hover:bg-slate-100">{{ __('BOQ') }} ({{ $project->boqItems->count() }})</button>
    <button type="button" data-tab="budget" class="project-tab rounded-lg px-3 py-1.5 text-slate-600 hover:bg-slate-100">{{ __('Budget') }} ({{ $project->budgetLines->count() }})</button>
    <button type="button" data-tab="costs" class="project-tab rounded-lg px-3 py-1.5 text-slate-600 hover:bg-slate-100">{{ __('Costs') }} ({{ $project->projectCosts->count() }})</button>
    <button type="button" data-tab="timesheets" class="project-tab rounded-lg px-3 py-1.5 text-slate-600 hover:bg-slate-100">{{ __('Timesheets') }} ({{ $project->timesheets->count() }})</button>
    <button type="button" data-tab="materials" class="project-tab rounded-lg px-3 py-1.5 text-slate-600 hover:bg-slate-100">{{ __('Materials') }} ({{ $project->materialIssues->count() }})</button>
    <button type="button" data-tab="equipment" class="project-tab rounded-lg px-3 py-1.5 text-slate-600 hover:bg-slate-100">{{ __('Equipment') }} ({{ $project->machineryDeployments->count() }})</button>
    <button type="button" data-tab="subcontracts" class="project-tab rounded-lg px-3 py-1.5 text-slate-600 hover:bg-slate-100">{{ __('Subcontracts') }} ({{ $project->subcontracts->count() }})</button>
    <button type="button" data-tab="change-orders" class="project-tab rounded-lg px-3 py-1.5 text-slate-600 hover:bg-slate-100">{{ __('Change Orders') }} ({{ $project->changeOrders->count() }})</button>
    <button type="button" data-tab="site-diary" class="project-tab rounded-lg px-3 py-1.5 text-slate-600 hover:bg-slate-100">{{ __('Site Diary') }} ({{ $project->siteDiaryEntries->count() }})</button>
    <button type="button" data-tab="work-orders" class="project-tab rounded-lg px-3 py-1.5 text-slate-600 hover:bg-slate-100">{{ __('Work Orders') }} ({{ $project->workOrders->count() }})</button>
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
            <div>
                <dt class="text-xs text-slate-400">{{ __('Contract value') }}</dt>
                <dd class="text-slate-800 mt-0.5">
                    {{ $project->contract_value ? \App\Support\Money::format($project->contract_value) : '—' }}
                    @if ($project->changeOrders->where('status', 'approved')->isNotEmpty())
                        <span class="text-xs text-slate-400">({{ __('effective') }}: {{ \App\Support\Money::format($project->effectiveContractValue()) }})</span>
                    @endif
                </dd>
            </div>
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

<div class="project-panel hidden" data-panel="tasks">
    <div class="bg-white rounded-xl border border-slate-100 p-6 mb-6">
        <h3 class="font-semibold text-slate-900 mb-4">{{ __('Add a task') }}</h3>
        <form method="POST" action="{{ route('app.projects.tasks.store', $project) }}" class="grid sm:grid-cols-4 gap-3 items-end">
            @csrf
            <div class="sm:col-span-2">
                <label class="block text-xs text-slate-500">{{ __('Title') }}</label>
                <input type="text" name="title" required class="mt-1 w-full rounded-lg border border-slate-200 text-sm">
            </div>
            <div>
                <label class="block text-xs text-slate-500">{{ __('Assignee') }}</label>
                <select name="assignee_employee_id" class="mt-1 w-full rounded-lg border border-slate-200 text-sm">
                    <option value="">{{ __('Unassigned') }}</option>
                    @foreach ($employees as $employee)
                        <option value="{{ $employee->id }}">{{ $employee->full_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs text-slate-500">{{ __('Due date') }}</label>
                <input type="date" name="due_date" class="mt-1 w-full rounded-lg border border-slate-200 text-sm">
            </div>
            <div class="sm:col-span-4">
                <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Add task') }}</button>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-xl border border-slate-100">
        @if ($project->tasks->isEmpty())
            <p class="px-6 py-8 text-sm text-slate-500">{{ __('No tasks yet.') }}</p>
        @else
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-slate-500 border-b border-slate-100">
                        <th class="px-6 py-3 font-medium">{{ __('Title') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('Assignee') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('Due date') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('Status') }}</th>
                        <th class="px-6 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($project->tasks as $task)
                        <tr class="border-b border-slate-50 last:border-0">
                            <td class="px-6 py-3 text-slate-900">{{ $task->title }}</td>
                            <td class="px-6 py-3 text-slate-500">{{ $task->assignee?->full_name ?? '—' }}</td>
                            <td class="px-6 py-3 {{ $task->isOverdue() ? 'text-red-600' : 'text-slate-500' }}">{{ optional($task->due_date)->format('Y-m-d') ?? '—' }}</td>
                            <td class="px-6 py-3">
                                <form method="POST" action="{{ route('app.projects.tasks.update', [$project, $task]) }}" class="inline">
                                    @csrf @method('PUT')
                                    <select name="status" onchange="this.form.submit()" class="rounded-lg border border-slate-200 text-xs">
                                        @foreach (['open' => __('Open'), 'in_progress' => __('In progress'), 'completed' => __('Completed'), 'cancelled' => __('Cancelled')] as $value => $label)
                                            <option value="{{ $value }}" @selected($task->status === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </form>
                            </td>
                            <td class="px-6 py-3 text-right">
                                <form method="POST" action="{{ route('app.projects.tasks.destroy', [$project, $task]) }}" class="inline">
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

<div class="project-panel hidden" data-panel="boq">
    <div class="bg-white rounded-xl border border-slate-100 p-6 mb-6">
        <h3 class="font-semibold text-slate-900 mb-4">{{ __('Add a BOQ item') }}</h3>
        <form method="POST" action="{{ route('app.projects.boq-items.store', $project) }}" class="grid sm:grid-cols-6 gap-3 items-end">
            @csrf
            <div>
                <label class="block text-xs text-slate-500">{{ __('Item no.') }}</label>
                <input type="text" name="item_no" class="mt-1 w-full rounded-lg border border-slate-200 text-sm">
            </div>
            <div class="sm:col-span-2">
                <label class="block text-xs text-slate-500">{{ __('Description') }}</label>
                <input type="text" name="description" required class="mt-1 w-full rounded-lg border border-slate-200 text-sm">
            </div>
            <div>
                <label class="block text-xs text-slate-500">{{ __('Unit') }}</label>
                <select name="unit_id" class="mt-1 w-full rounded-lg border border-slate-200 text-sm">
                    <option value="">{{ __('—') }}</option>
                    @foreach ($units as $unit)
                        <option value="{{ $unit->id }}">{{ $unit->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs text-slate-500">{{ __('Quantity') }}</label>
                <input type="number" step="0.01" min="0.01" name="quantity" required class="mt-1 w-full rounded-lg border border-slate-200 text-sm">
            </div>
            <div>
                <label class="block text-xs text-slate-500">{{ __('Rate') }}</label>
                <input type="number" step="0.01" min="0" name="rate" required class="mt-1 w-full rounded-lg border border-slate-200 text-sm">
            </div>
            <div class="sm:col-span-6">
                <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Add item') }}</button>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-xl border border-slate-100">
        @if ($project->boqItems->isEmpty())
            <p class="px-6 py-8 text-sm text-slate-500">{{ __('No BOQ items yet.') }}</p>
        @else
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-slate-500 border-b border-slate-100">
                        <th class="px-6 py-3 font-medium">{{ __('Item') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('Description') }}</th>
                        <th class="px-6 py-3 font-medium text-end">{{ __('Qty') }}</th>
                        <th class="px-6 py-3 font-medium text-end">{{ __('Rate') }}</th>
                        <th class="px-6 py-3 font-medium text-end">{{ __('Amount') }}</th>
                        <th class="px-6 py-3 font-medium text-end">{{ __('% complete') }}</th>
                        <th class="px-6 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($project->boqItems as $item)
                        <tr class="border-b border-slate-50 last:border-0">
                            <td class="px-6 py-3 text-slate-500">{{ $item->item_no ?: '—' }}</td>
                            <td class="px-6 py-3 text-slate-900">{{ $item->description }} @if ($item->unit)<span class="text-slate-400">({{ $item->unit->label() }})</span>@endif</td>
                            <td class="px-6 py-3 text-end">{{ number_format($item->quantity, 2) }}</td>
                            <td class="px-6 py-3 text-end">{{ \App\Support\Money::format($item->rate) }}</td>
                            <td class="px-6 py-3 text-end font-medium">{{ \App\Support\Money::format($item->amount()) }}</td>
                            <td class="px-6 py-3 text-end">
                                <form method="POST" action="{{ route('app.projects.boq-items.update', [$project, $item]) }}" class="flex items-center justify-end gap-1">
                                    @csrf @method('PUT')
                                    <input type="number" step="0.01" min="0" name="executed_quantity" value="{{ $item->executed_quantity }}" class="w-20 rounded-lg border border-slate-200 text-xs text-end">
                                    <input type="hidden" name="billed_quantity" value="{{ $item->billed_quantity }}">
                                    <button type="submit" class="text-xs text-brand-700 hover:underline">{{ __('Save') }}</button>
                                </form>
                                <p class="text-xs text-slate-400 mt-0.5">{{ $item->percentComplete() }}%</p>
                            </td>
                            <td class="px-6 py-3 text-right">
                                <form method="POST" action="{{ route('app.projects.boq-items.destroy', [$project, $item]) }}" class="inline">
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

<div class="project-panel hidden" data-panel="budget">
    <div class="bg-white rounded-xl border border-slate-100 p-6 mb-6">
        <h3 class="font-semibold text-slate-900 mb-4">{{ __('Add a budget line') }}</h3>
        <form method="POST" action="{{ route('app.projects.budget-lines.store', $project) }}" class="grid sm:grid-cols-4 gap-3 items-end">
            @csrf
            <div>
                <label class="block text-xs text-slate-500">{{ __('Cost head') }}</label>
                <select name="cost_head" required class="mt-1 w-full rounded-lg border border-slate-200 text-sm">
                    @foreach (\App\Models\Project::COST_HEADS as $head)
                        <option value="{{ $head }}">{{ __(ucfirst($head)) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sm:col-span-2">
                <label class="block text-xs text-slate-500">{{ __('Description') }}</label>
                <input type="text" name="description" class="mt-1 w-full rounded-lg border border-slate-200 text-sm">
            </div>
            <div>
                <label class="block text-xs text-slate-500">{{ __('Budgeted amount') }}</label>
                <input type="number" step="0.01" min="0" name="budgeted_amount" required class="mt-1 w-full rounded-lg border border-slate-200 text-sm">
            </div>
            <div class="sm:col-span-4">
                <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Add line') }}</button>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-xl border border-slate-100">
        @if ($project->budgetLines->isEmpty())
            <p class="px-6 py-8 text-sm text-slate-500">{{ __('No budget lines yet.') }}</p>
        @else
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-slate-500 border-b border-slate-100">
                        <th class="px-6 py-3 font-medium">{{ __('Cost head') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('Description') }}</th>
                        <th class="px-6 py-3 font-medium text-end">{{ __('Budgeted') }}</th>
                        <th class="px-6 py-3 font-medium text-end">{{ __('Actual') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('Status') }}</th>
                        <th class="px-6 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($project->budgetLines as $line)
                        @php($actual = $line->actualAmount())
                        <tr class="border-b border-slate-50 last:border-0">
                            <td class="px-6 py-3 text-slate-900">{{ ucfirst($line->cost_head) }}</td>
                            <td class="px-6 py-3 text-slate-500">{{ $line->description ?: '—' }}</td>
                            <td class="px-6 py-3 text-end">{{ \App\Support\Money::format($line->budgeted_amount) }}</td>
                            <td class="px-6 py-3 text-end {{ $actual > $line->budgeted_amount ? 'text-red-600' : 'text-slate-700' }}">{{ \App\Support\Money::format($actual) }}</td>
                            <td class="px-6 py-3">
                                @if ($line->status === 'approved')
                                    <span class="inline-block rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700">{{ __('Approved') }}</span>
                                @else
                                    <form method="POST" action="{{ route('app.projects.budget-lines.approve', [$project, $line]) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="text-xs font-semibold text-brand-700 hover:underline">{{ __('Approve') }}</button>
                                    </form>
                                @endif
                            </td>
                            <td class="px-6 py-3 text-right">
                                <form method="POST" action="{{ route('app.projects.budget-lines.destroy', [$project, $line]) }}" class="inline">
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

<div class="project-panel hidden" data-panel="costs">
    <div class="bg-white rounded-xl border border-slate-100 p-6 mb-6">
        <h3 class="font-semibold text-slate-900 mb-4">{{ __('Add a cost') }}</h3>
        <form method="POST" action="{{ route('app.projects.costs.store', $project) }}" class="grid sm:grid-cols-6 gap-3 items-end">
            @csrf
            <div>
                <label class="block text-xs text-slate-500">{{ __('Cost head') }}</label>
                <select name="cost_head" required class="mt-1 w-full rounded-lg border border-slate-200 text-sm">
                    @foreach (\App\Models\Project::COST_HEADS as $head)
                        <option value="{{ $head }}">{{ __(ucfirst($head)) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs text-slate-500">{{ __('Date') }}</label>
                <input type="date" name="cost_date" required value="{{ now()->toDateString() }}" class="mt-1 w-full rounded-lg border border-slate-200 text-sm">
            </div>
            <div class="sm:col-span-2">
                <label class="block text-xs text-slate-500">{{ __('Description') }}</label>
                <input type="text" name="description" required class="mt-1 w-full rounded-lg border border-slate-200 text-sm">
            </div>
            <div>
                <label class="block text-xs text-slate-500">{{ __('Amount') }}</label>
                <input type="number" step="0.01" min="0" name="amount" required class="mt-1 w-full rounded-lg border border-slate-200 text-sm">
            </div>
            <div class="flex items-center gap-2">
                <input type="checkbox" name="billable" value="1" checked id="cost-billable" class="rounded border-slate-300">
                <label for="cost-billable" class="text-xs text-slate-500">{{ __('Billable') }}</label>
            </div>
            <div class="sm:col-span-6">
                <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Add cost') }}</button>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-xl border border-slate-100">
        @if ($project->projectCosts->isEmpty())
            <p class="px-6 py-8 text-sm text-slate-500">{{ __('No costs logged yet.') }}</p>
        @else
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-slate-500 border-b border-slate-100">
                        <th class="px-6 py-3 font-medium">{{ __('Date') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('Cost head') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('Description') }}</th>
                        <th class="px-6 py-3 font-medium text-end">{{ __('Amount') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('Billable') }}</th>
                        <th class="px-6 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($project->projectCosts as $cost)
                        <tr class="border-b border-slate-50 last:border-0">
                            <td class="px-6 py-3 text-slate-500">{{ $cost->cost_date->format('Y-m-d') }}</td>
                            <td class="px-6 py-3 text-slate-900">{{ ucfirst($cost->cost_head) }}</td>
                            <td class="px-6 py-3 text-slate-500">{{ $cost->description }}</td>
                            <td class="px-6 py-3 text-end font-medium">{{ \App\Support\Money::format($cost->amount) }}</td>
                            <td class="px-6 py-3">{{ $cost->billable ? __('Yes') : __('No') }}</td>
                            <td class="px-6 py-3 text-right">
                                <form method="POST" action="{{ route('app.projects.costs.destroy', [$project, $cost]) }}" class="inline">
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

<div class="project-panel hidden" data-panel="timesheets">
    <div class="bg-white rounded-xl border border-slate-100 p-6 mb-6">
        <h3 class="font-semibold text-slate-900 mb-4">{{ __('Add a timesheet entry') }}</h3>
        <form method="POST" action="{{ route('app.projects.timesheets.store', $project) }}" class="grid sm:grid-cols-6 gap-3 items-end">
            @csrf
            <div>
                <label class="block text-xs text-slate-500">{{ __('Employee') }}</label>
                <select name="employee_id" required class="mt-1 w-full rounded-lg border border-slate-200 text-sm">
                    @foreach ($employees as $employee)
                        <option value="{{ $employee->id }}">{{ $employee->full_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs text-slate-500">{{ __('Date') }}</label>
                <input type="date" name="work_date" required value="{{ now()->toDateString() }}" class="mt-1 w-full rounded-lg border border-slate-200 text-sm">
            </div>
            <div class="sm:col-span-2">
                <label class="block text-xs text-slate-500">{{ __('Description') }}</label>
                <input type="text" name="description" class="mt-1 w-full rounded-lg border border-slate-200 text-sm">
            </div>
            <div>
                <label class="block text-xs text-slate-500">{{ __('Hours') }}</label>
                <input type="number" step="0.25" min="0.01" max="24" name="hours" required class="mt-1 w-full rounded-lg border border-slate-200 text-sm">
            </div>
            <div>
                <label class="block text-xs text-slate-500">{{ __('Overtime') }}</label>
                <input type="number" step="0.25" min="0" max="24" name="overtime_hours" class="mt-1 w-full rounded-lg border border-slate-200 text-sm">
            </div>
            <div>
                <label class="block text-xs text-slate-500">{{ __('Cost rate / hr') }}</label>
                <input type="number" step="0.01" min="0" name="cost_rate" class="mt-1 w-full rounded-lg border border-slate-200 text-sm">
            </div>
            <div>
                <label class="block text-xs text-slate-500">{{ __('Bill rate / hr') }}</label>
                <input type="number" step="0.01" min="0" name="bill_rate" class="mt-1 w-full rounded-lg border border-slate-200 text-sm">
            </div>
            <div class="sm:col-span-6">
                <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Add entry') }}</button>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-xl border border-slate-100">
        @if ($project->timesheets->isEmpty())
            <p class="px-6 py-8 text-sm text-slate-500">{{ __('No timesheet entries yet.') }}</p>
        @else
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-slate-500 border-b border-slate-100">
                        <th class="px-6 py-3 font-medium">{{ __('Date') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('Employee') }}</th>
                        <th class="px-6 py-3 font-medium text-end">{{ __('Hours') }}</th>
                        <th class="px-6 py-3 font-medium text-end">{{ __('Cost') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('Status') }}</th>
                        <th class="px-6 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($project->timesheets as $timesheet)
                        <tr class="border-b border-slate-50 last:border-0">
                            <td class="px-6 py-3 text-slate-500">{{ $timesheet->work_date->format('Y-m-d') }}</td>
                            <td class="px-6 py-3 text-slate-900">{{ $timesheet->employee?->full_name ?? '—' }}</td>
                            <td class="px-6 py-3 text-end">{{ $timesheet->totalHours() }}</td>
                            <td class="px-6 py-3 text-end">{{ \App\Support\Money::format($timesheet->costAmount()) }}</td>
                            <td class="px-6 py-3">
                                @if ($timesheet->status === 'approved')
                                    <span class="inline-block rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700">{{ __('Approved') }}</span>
                                @else
                                    <form method="POST" action="{{ route('app.projects.timesheets.approve', [$project, $timesheet]) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="text-xs font-semibold text-brand-700 hover:underline">{{ __('Approve') }}</button>
                                    </form>
                                @endif
                            </td>
                            <td class="px-6 py-3 text-right">
                                <form method="POST" action="{{ route('app.projects.timesheets.destroy', [$project, $timesheet]) }}" class="inline">
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

<div class="project-panel hidden" data-panel="materials">
    <div class="bg-white rounded-xl border border-slate-100 p-6 mb-6">
        <h3 class="font-semibold text-slate-900 mb-4">{{ __('Issue material') }}</h3>
        <form method="POST" action="{{ route('app.projects.material-issues.store', $project) }}" class="grid sm:grid-cols-6 gap-3 items-end">
            @csrf
            <div class="sm:col-span-2">
                <label class="block text-xs text-slate-500">{{ __('Item') }}</label>
                <select name="item_id" required class="mt-1 w-full rounded-lg border border-slate-200 text-sm">
                    @foreach ($items as $item)
                        <option value="{{ $item->id }}">{{ $item->display_name ?? $item->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs text-slate-500">{{ __('Warehouse') }}</label>
                <select name="warehouse_id" class="mt-1 w-full rounded-lg border border-slate-200 text-sm">
                    <option value="">{{ __('—') }}</option>
                    @foreach ($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs text-slate-500">{{ __('Quantity') }}</label>
                <input type="number" step="0.01" min="0.01" name="quantity" required class="mt-1 w-full rounded-lg border border-slate-200 text-sm">
            </div>
            <div>
                <label class="block text-xs text-slate-500">{{ __('Issued to') }}</label>
                <input type="text" name="issued_to" class="mt-1 w-full rounded-lg border border-slate-200 text-sm">
            </div>
            <div>
                <label class="block text-xs text-slate-500">{{ __('Date') }}</label>
                <input type="date" name="issued_at" required value="{{ now()->toDateString() }}" class="mt-1 w-full rounded-lg border border-slate-200 text-sm">
            </div>
            <div class="sm:col-span-6">
                <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Issue material') }}</button>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-xl border border-slate-100">
        @if ($project->materialIssues->isEmpty())
            <p class="px-6 py-8 text-sm text-slate-500">{{ __('No materials issued yet.') }}</p>
        @else
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-slate-500 border-b border-slate-100">
                        <th class="px-6 py-3 font-medium">{{ __('Date') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('Item') }}</th>
                        <th class="px-6 py-3 font-medium text-end">{{ __('Qty') }}</th>
                        <th class="px-6 py-3 font-medium text-end">{{ __('Unit cost') }}</th>
                        <th class="px-6 py-3 font-medium text-end">{{ __('Total') }}</th>
                        <th class="px-6 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($project->materialIssues as $issue)
                        <tr class="border-b border-slate-50 last:border-0">
                            <td class="px-6 py-3 text-slate-500">{{ $issue->issued_at->format('Y-m-d') }}</td>
                            <td class="px-6 py-3 text-slate-900">{{ $issue->item->name ?? '—' }}</td>
                            <td class="px-6 py-3 text-end">{{ number_format($issue->quantity, 2) }}</td>
                            <td class="px-6 py-3 text-end">{{ \App\Support\Money::format($issue->unit_cost) }}</td>
                            <td class="px-6 py-3 text-end font-medium">{{ \App\Support\Money::format($issue->total) }}</td>
                            <td class="px-6 py-3 text-right">
                                <form method="POST" action="{{ route('app.projects.material-issues.destroy', [$project, $issue]) }}" class="inline">
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

<div class="project-panel hidden" data-panel="equipment">
    <div class="flex items-center justify-between mb-4">
        <p class="text-sm text-slate-500">{{ __('Machinery deployed on this project — rate x days posts as project cost once a deployment ends.') }}</p>
        <a href="{{ route('app.machinery.deployments.create') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Deploy a machine') }}</a>
    </div>
    <div class="bg-white rounded-xl border border-slate-100">
        @if ($project->machineryDeployments->isEmpty())
            <p class="px-6 py-8 text-sm text-slate-500">{{ __('No machinery deployed on this project yet.') }}</p>
        @else
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-slate-500 border-b border-slate-100">
                        <th class="px-6 py-3 font-medium">{{ __('Machine') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('Start') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('End') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('Status') }}</th>
                        <th class="px-6 py-3 font-medium text-end">{{ __('Cost') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($project->machineryDeployments as $deployment)
                        <tr class="border-b border-slate-50 last:border-0">
                            <td class="px-6 py-3 text-slate-900">{{ $deployment->machinery->name }} <span class="text-slate-400">({{ $deployment->machinery->asset_code }})</span></td>
                            <td class="px-6 py-3 text-slate-500">{{ $deployment->start_date->format('Y-m-d') }}</td>
                            <td class="px-6 py-3 text-slate-500">{{ optional($deployment->end_date)->format('Y-m-d') ?? '—' }}</td>
                            <td class="px-6 py-3">{{ ucfirst($deployment->status) }}</td>
                            <td class="px-6 py-3 text-end font-medium">{{ \App\Support\Money::format($deployment->notionalCost()) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>

<div class="project-panel hidden" data-panel="subcontracts">
    <div class="flex items-center justify-between mb-4">
        <p class="text-sm text-slate-500">{{ __('Sub-vendor contracts raised against this project.') }}</p>
        <a href="{{ route('app.subcontracts.create') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('New subcontract') }}</a>
    </div>
    <div class="bg-white rounded-xl border border-slate-100">
        @if ($project->subcontracts->isEmpty())
            <p class="px-6 py-8 text-sm text-slate-500">{{ __('No subcontracts raised against this project yet.') }}</p>
        @else
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-slate-500 border-b border-slate-100">
                        <th class="px-6 py-3 font-medium">{{ __('Contract #') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('Supplier') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('Title') }}</th>
                        <th class="px-6 py-3 font-medium text-end">{{ __('Value') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($project->subcontracts as $subcontract)
                        <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50 cursor-pointer" onclick="window.location='{{ route('app.subcontracts.show', $subcontract) }}'">
                            <td class="px-6 py-3 font-medium text-brand-700">{{ $subcontract->contract_number }}</td>
                            <td class="px-6 py-3 text-slate-900">{{ $subcontract->supplier->display_name ?? '—' }}</td>
                            <td class="px-6 py-3 text-slate-500">{{ $subcontract->title }}</td>
                            <td class="px-6 py-3 text-end font-medium">{{ \App\Support\Money::format($subcontract->contract_value) }}</td>
                            <td class="px-6 py-3">{{ ucfirst($subcontract->status) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>

<div class="project-panel hidden" data-panel="change-orders">
    <div class="bg-white rounded-xl border border-slate-100 p-6 mb-6">
        <h3 class="font-semibold text-slate-900 mb-4">{{ __('Add a change order') }}</h3>
        <form method="POST" action="{{ route('app.projects.change-orders.store', $project) }}" class="grid sm:grid-cols-6 gap-3 items-end">
            @csrf
            <div class="sm:col-span-2">
                <label class="block text-xs text-slate-500">{{ __('Title') }}</label>
                <input type="text" name="title" required class="mt-1 w-full rounded-lg border border-slate-200 text-sm">
            </div>
            <div>
                <label class="block text-xs text-slate-500">{{ __('Amount (+/-)') }}</label>
                <input type="number" step="0.01" name="amount" required class="mt-1 w-full rounded-lg border border-slate-200 text-sm">
            </div>
            <div>
                <label class="block text-xs text-slate-500">{{ __('Cost impact') }}</label>
                <input type="number" step="0.01" name="cost_impact" class="mt-1 w-full rounded-lg border border-slate-200 text-sm">
            </div>
            <div>
                <label class="block text-xs text-slate-500">{{ __('Days impact') }}</label>
                <input type="number" step="1" name="days_impact" class="mt-1 w-full rounded-lg border border-slate-200 text-sm">
            </div>
            <div>
                <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Add') }}</button>
            </div>
            <div class="sm:col-span-6">
                <label class="block text-xs text-slate-500">{{ __('Description') }}</label>
                <textarea name="description" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 text-sm"></textarea>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-xl border border-slate-100">
        @if ($project->changeOrders->isEmpty())
            <p class="px-6 py-8 text-sm text-slate-500">{{ __('No change orders yet.') }}</p>
        @else
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-slate-500 border-b border-slate-100">
                        <th class="px-6 py-3 font-medium">{{ __('No.') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('Title') }}</th>
                        <th class="px-6 py-3 font-medium text-end">{{ __('Amount') }}</th>
                        <th class="px-6 py-3 font-medium text-end">{{ __('Days') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('Status') }}</th>
                        <th class="px-6 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($project->changeOrders as $changeOrder)
                        <tr class="border-b border-slate-50 last:border-0">
                            <td class="px-6 py-3 text-slate-500">{{ $changeOrder->change_order_number }}</td>
                            <td class="px-6 py-3 text-slate-900">{{ $changeOrder->title }}</td>
                            <td class="px-6 py-3 text-end {{ $changeOrder->amount < 0 ? 'text-red-600' : 'text-emerald-700' }}">{{ \App\Support\Money::format($changeOrder->amount) }}</td>
                            <td class="px-6 py-3 text-end">{{ $changeOrder->days_impact ?? '—' }}</td>
                            <td class="px-6 py-3">
                                @if ($changeOrder->status === 'draft')
                                    <form method="POST" action="{{ route('app.projects.change-orders.approve', [$project, $changeOrder]) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="text-xs font-semibold text-emerald-700 hover:underline">{{ __('Approve') }}</button>
                                    </form>
                                    <form method="POST" action="{{ route('app.projects.change-orders.reject', [$project, $changeOrder]) }}" class="inline ms-2">
                                        @csrf
                                        <button type="submit" class="text-xs font-semibold text-red-600 hover:underline">{{ __('Reject') }}</button>
                                    </form>
                                @else
                                    <span class="inline-block rounded-full px-2.5 py-1 text-xs font-medium {{ $changeOrder->status === 'approved' ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700' }}">{{ ucfirst($changeOrder->status) }}</span>
                                @endif
                            </td>
                            <td class="px-6 py-3 text-right">
                                @if ($changeOrder->status === 'draft')
                                    <form method="POST" action="{{ route('app.projects.change-orders.destroy', [$project, $changeOrder]) }}" class="inline">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-xs font-semibold text-red-600 hover:underline">{{ __('Remove') }}</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>

<div class="project-panel hidden" data-panel="site-diary">
    <div class="bg-white rounded-xl border border-slate-100 p-6 mb-6">
        <h3 class="font-semibold text-slate-900 mb-4">{{ __('Add a site diary entry') }}</h3>
        <form method="POST" action="{{ route('app.projects.site-diary.store', $project) }}" class="grid sm:grid-cols-6 gap-3 items-end">
            @csrf
            <div>
                <label class="block text-xs text-slate-500">{{ __('Date') }}</label>
                <input type="date" name="entry_date" required value="{{ now()->toDateString() }}" class="mt-1 w-full rounded-lg border border-slate-200 text-sm">
            </div>
            <div>
                <label class="block text-xs text-slate-500">{{ __('Weather') }}</label>
                <input type="text" name="weather" class="mt-1 w-full rounded-lg border border-slate-200 text-sm">
            </div>
            <div>
                <label class="block text-xs text-slate-500">{{ __('Manpower') }}</label>
                <input type="number" min="0" name="manpower_count" class="mt-1 w-full rounded-lg border border-slate-200 text-sm">
            </div>
            <div class="sm:col-span-3"></div>
            <div class="sm:col-span-3">
                <label class="block text-xs text-slate-500">{{ __('Work done') }}</label>
                <textarea name="work_done" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 text-sm"></textarea>
            </div>
            <div class="sm:col-span-3">
                <label class="block text-xs text-slate-500">{{ __('Materials received') }}</label>
                <textarea name="materials_received" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 text-sm"></textarea>
            </div>
            <div class="sm:col-span-5">
                <label class="block text-xs text-slate-500">{{ __('Issues / delays') }}</label>
                <textarea name="issues_delays" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 text-sm"></textarea>
            </div>
            <div>
                <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Add') }}</button>
            </div>
        </form>
    </div>

    <div class="space-y-3">
        @forelse ($project->siteDiaryEntries as $entry)
            <div class="bg-white rounded-xl border border-slate-100 p-5">
                <div class="flex items-center justify-between">
                    <p class="font-semibold text-slate-900">{{ $entry->entry_date->format('Y-m-d') }} @if ($entry->weather)<span class="text-slate-400 font-normal">— {{ $entry->weather }}</span>@endif</p>
                    <form method="POST" action="{{ route('app.projects.site-diary.destroy', [$project, $entry]) }}" class="inline">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-xs font-semibold text-red-600 hover:underline">{{ __('Remove') }}</button>
                    </form>
                </div>
                @if ($entry->manpower_count !== null)<p class="text-xs text-slate-400 mt-1">{{ __('Manpower') }}: {{ $entry->manpower_count }}</p>@endif
                @if ($entry->work_done)<p class="text-sm text-slate-700 mt-2"><span class="font-medium">{{ __('Work done') }}:</span> {{ $entry->work_done }}</p>@endif
                @if ($entry->materials_received)<p class="text-sm text-slate-700 mt-1"><span class="font-medium">{{ __('Materials received') }}:</span> {{ $entry->materials_received }}</p>@endif
                @if ($entry->issues_delays)<p class="text-sm text-red-600 mt-1"><span class="font-medium">{{ __('Issues/delays') }}:</span> {{ $entry->issues_delays }}</p>@endif
            </div>
        @empty
            <p class="px-6 py-8 text-sm text-slate-500">{{ __('No site diary entries yet.') }}</p>
        @endforelse
    </div>
</div>

<div class="project-panel hidden" data-panel="work-orders">
    <div class="bg-white rounded-xl border border-slate-100 p-6 mb-6">
        <h3 class="font-semibold text-slate-900 mb-4">{{ __('Create a work order') }}</h3>
        <form method="POST" action="{{ route('app.projects.work-orders.store', $project) }}" class="grid sm:grid-cols-6 gap-3 items-end">
            @csrf
            <div class="sm:col-span-2">
                <label class="block text-xs text-slate-500">{{ __('Title') }}</label>
                <input type="text" name="title" required class="mt-1 w-full rounded-lg border border-slate-200 text-sm">
            </div>
            <div>
                <label class="block text-xs text-slate-500">{{ __('Kind') }}</label>
                <select name="kind" class="mt-1 w-full rounded-lg border border-slate-200 text-sm">
                    @foreach (\App\Models\ProjectWorkOrder::KINDS as $kind)
                        <option value="{{ $kind }}">{{ __(ucfirst($kind)) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs text-slate-500">{{ __('Priority') }}</label>
                <select name="priority" class="mt-1 w-full rounded-lg border border-slate-200 text-sm">
                    @foreach (\App\Models\ProjectWorkOrder::PRIORITIES as $priority)
                        <option value="{{ $priority }}" @selected($priority === 'medium')>{{ __(ucfirst($priority)) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs text-slate-500">{{ __('Machine (optional)') }}</label>
                <select name="machinery_asset_id" class="mt-1 w-full rounded-lg border border-slate-200 text-sm">
                    <option value="">{{ __('—') }}</option>
                    @foreach ($machineryAssets as $asset)
                        <option value="{{ $asset->id }}">{{ $asset->name }} ({{ $asset->asset_code }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs text-slate-500">{{ __('Technician') }}</label>
                <select name="technician_employee_id" class="mt-1 w-full rounded-lg border border-slate-200 text-sm">
                    <option value="">{{ __('Unassigned') }}</option>
                    @foreach ($employees as $employee)
                        <option value="{{ $employee->id }}">{{ $employee->full_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs text-slate-500">{{ __('Raised date') }}</label>
                <input type="date" name="raised_date" required value="{{ now()->toDateString() }}" class="mt-1 w-full rounded-lg border border-slate-200 text-sm">
            </div>
            <div>
                <label class="block text-xs text-slate-500">{{ __('Scheduled for') }}</label>
                <input type="datetime-local" name="scheduled_at" class="mt-1 w-full rounded-lg border border-slate-200 text-sm">
            </div>
            <div>
                <label class="block text-xs text-slate-500">{{ __('Duration (min)') }}</label>
                <input type="number" min="1" name="duration_minutes" class="mt-1 w-full rounded-lg border border-slate-200 text-sm">
            </div>
            <div class="sm:col-span-6">
                <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Create work order') }}</button>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-xl border border-slate-100">
        @if ($project->workOrders->isEmpty())
            <p class="px-6 py-8 text-sm text-slate-500">{{ __('No work orders yet.') }}</p>
        @else
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-slate-500 border-b border-slate-100">
                        <th class="px-6 py-3 font-medium">{{ __('No.') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('Title') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('Technician') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('Scheduled') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('Status') }}</th>
                        <th class="px-6 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($project->workOrders as $workOrder)
                        <tr class="border-b border-slate-50 last:border-0">
                            <td class="px-6 py-3 text-slate-500">{{ $workOrder->work_order_number }}</td>
                            <td class="px-6 py-3 text-slate-900">{{ $workOrder->title }} <span class="text-xs {{ $workOrder->priority === 'urgent' ? 'text-red-600' : 'text-slate-400' }}">({{ ucfirst($workOrder->priority) }})</span></td>
                            <td class="px-6 py-3 text-slate-500">{{ $workOrder->technician?->full_name ?? '—' }}</td>
                            <td class="px-6 py-3 {{ $workOrder->isOverdue() ? 'text-red-600' : 'text-slate-500' }}">{{ optional($workOrder->scheduled_at)->format('Y-m-d H:i') ?? '—' }}</td>
                            <td class="px-6 py-3">
                                <form method="POST" action="{{ route('app.projects.work-orders.update', [$project, $workOrder]) }}" class="inline">
                                    @csrf @method('PUT')
                                    <input type="hidden" name="technician_employee_id" value="{{ $workOrder->technician_employee_id }}">
                                    <input type="hidden" name="scheduled_at" value="{{ $workOrder->scheduled_at }}">
                                    <select name="status" onchange="this.form.submit()" class="rounded-lg border border-slate-200 text-xs">
                                        @foreach (\App\Models\ProjectWorkOrder::STATUSES as $status)
                                            <option value="{{ $status }}" @selected($workOrder->status === $status)>{{ __(ucfirst(str_replace('_', ' ', $status))) }}</option>
                                        @endforeach
                                    </select>
                                </form>
                            </td>
                            <td class="px-6 py-3 text-right">
                                <form method="POST" action="{{ route('app.projects.work-orders.destroy', [$project, $workOrder]) }}" class="inline">
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
