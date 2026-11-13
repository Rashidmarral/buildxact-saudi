@extends('layouts.app')

@section('title', $contract->contract_number)

@section('content')
<div class="flex items-start justify-between mb-6">
    <div>
        <a href="{{ route('app.machinery.rental-contracts.index') }}" class="text-sm text-slate-400 hover:text-slate-600">{{ __('← Rental Contracts') }}</a>
        <h1 class="text-xl font-bold text-slate-900 mt-1">{{ $contract->contract_number }}</h1>
        <p class="text-sm text-slate-500 mt-1">
            <a href="{{ route('app.machinery.assets.show', $contract->machinery) }}" class="text-brand-700 hover:underline">{{ $contract->machinery->name }} ({{ $contract->machinery->asset_code }})</a>
            — {{ __('rented to') }} {{ $contract->renterDisplayName() }}
        </p>
    </div>
    <span class="inline-block rounded-full {{ $contract->status === 'active' ? 'bg-sky-50 text-sky-700' : 'bg-slate-100 text-slate-600' }} text-xs font-medium px-2.5 py-1">{{ ucfirst($contract->status) }}</span>
</div>

<div class="grid lg:grid-cols-2 gap-6">
    <div class="bg-white rounded-xl border border-slate-100 p-6">
        <h3 class="font-semibold text-slate-900 mb-3">{{ __('Contract details') }}</h3>
        <dl class="text-sm space-y-2">
            <div class="flex justify-between"><dt class="text-slate-500">{{ __('Period') }}</dt><dd>{{ $contract->start_date->format('Y-m-d') }} — {{ $contract->end_date?->format('Y-m-d') ?? __('ongoing') }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">{{ __('Rate') }}</dt><dd>{{ \App\Support\Money::format($contract->rate) }} / {{ __(ucfirst($contract->rate_type)) }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">{{ __('Deposit') }}</dt><dd>{{ $contract->deposit_amount ? \App\Support\Money::format($contract->deposit_amount) : '—' }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">{{ __('Operator') }}</dt><dd>{{ $contract->operator_included ? ($contract->operator?->full_name ?? __('Included')) : __('Not included') }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">{{ __('Fuel responsibility') }}</dt><dd>{{ $contract->fuel_responsibility === 'owner' ? __('Us (owner)') : __('Renter') }}</dd></div>
            @if ($contract->project)
                <div class="flex justify-between"><dt class="text-slate-500">{{ __('Project') }}</dt><dd>{{ $contract->project->name }}</dd></div>
            @endif
        </dl>
        @if ($contract->delivery_condition_notes)
            <p class="text-xs text-slate-400 mt-3">{{ __('Delivery notes') }}: {{ $contract->delivery_condition_notes }}</p>
        @endif
        @if ($contract->return_condition_notes)
            <p class="text-xs text-slate-400 mt-1">{{ __('Return notes') }}: {{ $contract->return_condition_notes }}</p>
        @endif
    </div>

    @if ($contract->status === 'active')
        <div class="bg-white rounded-xl border border-slate-100 p-6">
            <h3 class="font-semibold text-slate-900 mb-3">{{ __('Generate rental invoice') }}</h3>
            <p class="text-xs text-slate-500 mb-3">{{ __('Creates a draft invoice for one billing period — review and send it from the Invoices screen.') }}</p>
            <form method="POST" action="{{ route('app.machinery.rental-contracts.generate-invoice', $contract) }}" class="space-y-3">
                @csrf
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('From') }}</label>
                        <input type="date" name="period_start" value="{{ $contract->start_date->toDateString() }}" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('To') }}</label>
                        <input type="date" name="period_end" value="{{ now()->toDateString() }}" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500">
                        {{ match($contract->rate_type) { 'daily' => __('Number of days'), 'weekly' => __('Number of weeks'), 'monthly' => __('Number of months'), default => __('Number of units') } }}
                    </label>
                    <input type="number" step="0.5" min="0.5" name="units" value="1" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                </div>
                <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Generate invoice') }}</button>
            </form>

            <div class="border-t border-slate-100 mt-5 pt-5">
                <h4 class="font-semibold text-slate-900 mb-2">{{ __('End rental / return') }}</h4>
                <form method="POST" action="{{ route('app.machinery.rental-contracts.end', $contract) }}" class="space-y-3">
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
        </div>
    @endif
</div>

<div class="mt-6">
    <a href="{{ route('app.machinery.letters.create', ['machinery_rental_contract_id' => $contract->id, 'document_type' => 'machinery_rental_agreement']) }}" class="text-sm font-semibold text-brand-700 hover:underline">{{ __('Generate rental agreement letter') }}</a>
</div>
@endsection
