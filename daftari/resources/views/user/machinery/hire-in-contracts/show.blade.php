@extends('layouts.app')

@section('title', $contract->contract_number)

@section('content')
<div class="flex items-start justify-between mb-6">
    <div>
        <a href="{{ route('app.machinery.hire-in-contracts.index') }}" class="text-sm text-slate-400 hover:text-slate-600">{{ __('← Hired-in Equipment') }}</a>
        <h1 class="text-xl font-bold text-slate-900 mt-1">{{ $contract->contract_number }}</h1>
        <p class="text-sm text-slate-500 mt-1">{{ $contract->equipment_description }} — {{ __('hired from') }} {{ $contract->supplierDisplayName() }}</p>
    </div>
    <span class="inline-block rounded-full {{ $contract->status === 'active' ? 'bg-sky-50 text-sky-700' : 'bg-slate-100 text-slate-600' }} text-xs font-medium px-2.5 py-1">{{ ucfirst($contract->status) }}</span>
</div>

<div class="grid lg:grid-cols-2 gap-6">
    <div class="bg-white rounded-xl border border-slate-100 p-6">
        <h3 class="font-semibold text-slate-900 mb-3">{{ __('Contract details') }}</h3>
        <dl class="text-sm space-y-2">
            <div class="flex justify-between"><dt class="text-slate-500">{{ __('Period') }}</dt><dd>{{ $contract->start_date->format('Y-m-d') }} — {{ $contract->end_date?->format('Y-m-d') ?? __('ongoing') }}</dd></div>
            <div class="flex justify-between">
                <dt class="text-slate-500">{{ __('Rate') }}</dt>
                <dd>
                    {{ \App\Support\Money::format($contract->rate) }}
                    @if ($contract->rate_type === 'per_unit')
                        / {{ $contract->rate_unit_label }}
                    @else
                        / {{ __(ucfirst($contract->rate_type)) }}
                    @endif
                </dd>
            </div>
            <div class="flex justify-between"><dt class="text-slate-500">{{ __('Operator') }}</dt><dd>{{ $contract->operator_included ? ($contract->operator_name ?: __('Included')) : __('Not included') }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">{{ __('Fuel responsibility') }}</dt><dd>{{ $contract->fuel_responsibility === 'supplier' ? __('Supplier') : __('Us (company)') }}</dd></div>
            @if ($contract->plate_or_chassis_number)
                <div class="flex justify-between"><dt class="text-slate-500">{{ __('Plate / chassis') }}</dt><dd>{{ $contract->plate_or_chassis_number }}</dd></div>
            @endif
            @if ($contract->supplier_cr_number)
                <div class="flex justify-between"><dt class="text-slate-500">{{ __('Supplier C.R.') }}</dt><dd>{{ $contract->supplier_cr_number }}</dd></div>
            @endif
            @if ($contract->project)
                <div class="flex justify-between"><dt class="text-slate-500">{{ __('Project') }}</dt><dd>{{ $contract->project->name }}</dd></div>
            @endif
            <div class="flex justify-between border-t border-slate-100 pt-2 mt-2"><dt class="font-semibold text-slate-700">{{ __('Total cost recorded') }}</dt><dd class="font-semibold text-slate-900">{{ \App\Support\Money::format($contract->totalCost()) }}</dd></div>
        </dl>
        @if ($contract->delivery_condition_notes)
            <p class="text-xs text-slate-400 mt-3">{{ __('Delivery notes') }}: {{ $contract->delivery_condition_notes }}</p>
        @endif
        @if ($contract->return_condition_notes)
            <p class="text-xs text-slate-400 mt-1">{{ __('Return notes') }}: {{ $contract->return_condition_notes }}</p>
        @endif
    </div>

    <div class="bg-white rounded-xl border border-slate-100 p-6">
        <h3 class="font-semibold text-slate-900 mb-3">{{ __('Costs recorded against this contract') }}</h3>
        <a href="{{ route('app.expenses.create', ['machinery_hire_in_contract_id' => $contract->id, 'project_id' => $contract->project_id]) }}" class="inline-block rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700 mb-4">{{ __('+ Record an expense') }}</a>

        @if ($contract->expenses->isEmpty())
            <p class="text-sm text-slate-400">{{ __('No costs recorded yet.') }}</p>
        @else
            <ul class="divide-y divide-slate-50 text-sm">
                @foreach ($contract->expenses as $expense)
                    <li class="flex items-center justify-between py-2">
                        <div>
                            <a href="{{ route('app.expenses.edit', $expense) }}" class="text-brand-700 hover:underline">{{ $expense->description ?: $expense->vendor_name }}</a>
                            <p class="text-xs text-slate-400">{{ $expense->expense_date->format('Y-m-d') }} — {{ ucfirst($expense->status) }}</p>
                        </div>
                        <span class="font-medium text-slate-800">{{ \App\Support\Money::format($expense->gross_amount) }}</span>
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($contract->status === 'active')
            <div class="border-t border-slate-100 mt-5 pt-5">
                <h4 class="font-semibold text-slate-900 mb-2">{{ __('End hire / return') }}</h4>
                <form method="POST" action="{{ route('app.machinery.hire-in-contracts.end', $contract) }}" class="space-y-3">
                    @csrf
                    <div>
                        <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Return date') }}</label>
                        <input type="date" name="end_date" value="{{ now()->toDateString() }}" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Return condition notes') }}</label>
                        <textarea name="return_condition_notes" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500"></textarea>
                    </div>
                    <button type="submit" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Mark returned / end contract') }}</button>
                </form>
            </div>
        @endif
    </div>
</div>

<div class="mt-6">
    <a href="{{ route('app.machinery.letters.create', ['machinery_hire_in_contract_id' => $contract->id, 'document_type' => 'machinery_hire_in_agreement']) }}" class="text-sm font-semibold text-brand-700 hover:underline">{{ __('Generate hire-in agreement letter') }}</a>
</div>
@endsection
