@extends('layouts.app')

@section('title', __('New Rental Contract'))

@section('content')
<div class="max-w-2xl">
    <h1 class="text-xl font-bold text-slate-900 mb-1">{{ __('New Rental Contract') }}</h1>
    <p class="text-sm text-slate-500 mb-6">{{ __('Marks the machine rented out. Revenue is billed separately from the contract page once you generate its invoice.') }}</p>

    <form method="POST" action="{{ route('app.machinery.rental-contracts.store') }}" class="bg-white rounded-xl border border-slate-100 p-6 space-y-4">
        @csrf

        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Machine') }}</label>
            <select name="machinery_asset_id" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                <option value="">{{ __('Select a machine') }}</option>
                @foreach ($machinery as $asset)
                    <option value="{{ $asset->id }}" @selected(old('machinery_asset_id', $selectedMachineryId) == $asset->id)>{{ $asset->name }} ({{ $asset->asset_code }})</option>
                @endforeach
            </select>
            @error('machinery_asset_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>

        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Renter (customer on file)') }}</label>
                <select name="client_id" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                    <option value="">{{ __('None — enter manually') }}</option>
                    @foreach ($clients as $client)
                        <option value="{{ $client->id }}" @selected(old('client_id') == $client->id)>{{ $client->display_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Renter name (if not on file)') }}</label>
                <input type="text" name="renter_name" value="{{ old('renter_name') }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                @error('renter_name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
        </div>

        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Renter phone') }}</label>
            <input type="text" name="renter_phone" value="{{ old('renter_phone') }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>

        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Start date') }}</label>
                <input type="date" name="start_date" value="{{ old('start_date', $contract->start_date) }}" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('End date (optional)') }}</label>
                <input type="date" name="end_date" value="{{ old('end_date') }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                <p class="text-xs text-slate-400 mt-1">{{ __('Leave blank for an open-ended rental until returned.') }}</p>
            </div>
        </div>

        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Rate') }}</label>
                <input type="number" step="0.01" min="0.01" name="rate" value="{{ old('rate') }}" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                @error('rate')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Rate type') }}</label>
                <select name="rate_type" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                    @foreach (['daily' => __('Daily'), 'weekly' => __('Weekly'), 'monthly' => __('Monthly')] as $value => $label)
                        <option value="{{ $value }}" @selected(old('rate_type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Security deposit (optional)') }}</label>
                <input type="number" step="0.01" min="0" name="deposit_amount" value="{{ old('deposit_amount') }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Fuel responsibility') }}</label>
                <select name="fuel_responsibility" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                    <option value="owner" @selected(old('fuel_responsibility', 'owner') === 'owner')>{{ __('Us (owner)') }}</option>
                    <option value="renter" @selected(old('fuel_responsibility') === 'renter')>{{ __('Renter') }}</option>
                </select>
            </div>
        </div>

        <div class="grid sm:grid-cols-2 gap-4">
            <label class="flex items-center gap-2 text-sm text-slate-600 mt-6">
                <input type="checkbox" name="operator_included" value="1" @checked(old('operator_included'))>
                {{ __('Operator included') }}
            </label>
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Operator (if included)') }}</label>
                <select name="operator_employee_id" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                    <option value="">{{ __('None') }}</option>
                    @foreach ($employees as $employee)
                        <option value="{{ $employee->id }}" @selected(old('operator_employee_id') == $employee->id)>{{ $employee->full_name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Project (optional)') }}</label>
            <select name="project_id" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                <option value="">{{ __('None') }}</option>
                @foreach ($projects as $project)
                    <option value="{{ $project->id }}" @selected(old('project_id') == $project->id)>{{ $project->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Delivery condition notes') }}</label>
            <textarea name="delivery_condition_notes" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">{{ old('delivery_condition_notes') }}</textarea>
        </div>

        <button type="submit" class="rounded-lg bg-brand-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Create rental contract') }}</button>
    </form>
</div>
@endsection
