@extends('layouts.app')

@section('title', __('New Hire-in Contract'))

@section('content')
<div class="max-w-2xl">
    <h1 class="text-xl font-bold text-slate-900 mb-1">{{ __('New Hire-in Contract') }}</h1>
    <p class="text-sm text-slate-500 mb-6">{{ __('For equipment you hire in from an external supplier — not your own machinery. Record costs (fuel, the supplier\'s billed amount) from the contract page once created.') }}</p>

    <form method="POST" action="{{ route('app.machinery.hire-in-contracts.store') }}" class="bg-white rounded-xl border border-slate-100 p-6 space-y-4">
        @csrf

        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Equipment description') }}</label>
            <input type="text" name="equipment_description" value="{{ old('equipment_description') }}" required placeholder="{{ __('e.g. MC1 & RC2 spray tanker') }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            @error('equipment_description')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>

        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Category (optional)') }}</label>
                <input type="text" name="equipment_category" value="{{ old('equipment_category') }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Plate / chassis number (optional)') }}</label>
                <input type="text" name="plate_or_chassis_number" value="{{ old('plate_or_chassis_number') }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
        </div>

        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Supplier (on file)') }}</label>
                <select name="supplier_id" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                    <option value="">{{ __('None — enter manually') }}</option>
                    @foreach ($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" @selected(old('supplier_id') == $supplier->id)>{{ $supplier->display_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Supplier name (if not on file)') }}</label>
                <input type="text" name="supplier_name" value="{{ old('supplier_name') }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                @error('supplier_name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Supplier C.R. number (optional)') }}</label>
                <input type="text" name="supplier_cr_number" value="{{ old('supplier_cr_number') }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Supplier phone') }}</label>
                <input type="text" name="supplier_phone" value="{{ old('supplier_phone') }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
        </div>

        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Start date') }}</label>
                <input type="date" name="start_date" value="{{ old('start_date', $contract->start_date) }}" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('End date (optional)') }}</label>
                <input type="date" name="end_date" value="{{ old('end_date') }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                <p class="text-xs text-slate-400 mt-1">{{ __('Leave blank for an open-ended hire until returned.') }}</p>
            </div>
        </div>

        <div class="grid sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Rate') }}</label>
                <input type="number" step="0.01" min="0.01" name="rate" value="{{ old('rate') }}" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                @error('rate')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Rate type') }}</label>
                <select name="rate_type" id="rate_type" required onchange="document.getElementById('rate-unit-wrap').classList.toggle('hidden', this.value !== 'per_unit')" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                    @foreach (['daily' => __('Daily'), 'weekly' => __('Weekly'), 'monthly' => __('Monthly'), 'per_unit' => __('Per unit (e.g. per m²)')] as $value => $label)
                        <option value="{{ $value }}" @selected(old('rate_type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div id="rate-unit-wrap" class="{{ old('rate_type') === 'per_unit' ? '' : 'hidden' }}">
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Unit') }}</label>
                <input type="text" name="rate_unit_label" value="{{ old('rate_unit_label') }}" placeholder="{{ __('e.g. sq. meter') }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                @error('rate_unit_label')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
        </div>

        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Fuel / maintenance responsibility') }}</label>
            <select name="fuel_responsibility" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                <option value="supplier" @selected(old('fuel_responsibility', 'supplier') === 'supplier')>{{ __('Supplier') }}</option>
                <option value="company" @selected(old('fuel_responsibility') === 'company')>{{ __('Us (company)') }}</option>
            </select>
        </div>

        <div class="grid sm:grid-cols-2 gap-4">
            <label class="flex items-center gap-2 text-sm text-slate-600 mt-6">
                <input type="checkbox" name="operator_included" value="1" @checked(old('operator_included', true))>
                {{ __('Operator/driver included (provided by supplier)') }}
            </label>
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Operator name (if known)') }}</label>
                <input type="text" name="operator_name" value="{{ old('operator_name') }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
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

        <button type="submit" class="rounded-lg bg-brand-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Create hire-in contract') }}</button>
    </form>
</div>
@endsection
