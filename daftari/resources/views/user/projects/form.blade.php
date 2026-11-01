@extends('layouts.app')

@section('title', $project->exists ? __('Edit project') : __('New project'))

@section('content')
<div class="mb-6">
    <h2 class="text-lg font-semibold text-slate-900">{{ $project->exists ? __('Edit project') : __('New project') }}</h2>
    <p class="text-sm text-slate-500 mt-1">{{ __('Create a project to track its revenue, costs, and margin.') }}</p>
</div>

<form method="POST" action="{{ $project->exists ? route('app.projects.update', $project) : route('app.projects.store') }}" class="bg-white rounded-xl border border-slate-100 p-6 max-w-3xl space-y-5">
    @csrf
    @if ($project->exists) @method('PUT') @endif

    <div class="grid sm:grid-cols-2 gap-5">
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Project code') }}</label>
            @if ($project->exists)
                <input type="text" value="{{ $project->code }}" disabled class="mt-1 w-full rounded-lg border border-slate-200 bg-slate-50 text-slate-400">
            @else
                <input type="text" name="code" value="{{ old('code') }}" placeholder="{{ $nextCodePreview }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                <p class="mt-1 text-xs text-slate-400">{{ __('Uppercase letters, digits, underscores, and hyphens only (e.g. PROJ-2026).') }}</p>
            @endif
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Project name') }}</label>
            <input type="text" name="name" value="{{ old('name', $project->name) }}" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
    </div>

    <div class="grid sm:grid-cols-2 gap-5">
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Type') }}</label>
            <select name="type" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                @foreach (['internal' => __('Internal'), 'service_tm' => __('Service / time & material'), 'construction_contract' => __('Construction contract'), 'real_estate_development' => __('Real estate development'), 'maintenance_amc' => __('Maintenance (AMC)')] as $value => $label)
                    <option value="{{ $value }}" @selected(old('type', $project->type) === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Status') }}</label>
            <select name="status" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                @foreach (['active' => __('Active'), 'on_hold' => __('On hold'), 'completed' => __('Completed'), 'cancelled' => __('Cancelled')] as $value => $label)
                    <option value="{{ $value }}" @selected(old('status', $project->status) === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="grid sm:grid-cols-2 gap-5">
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Customer') }}</label>
            <select name="client_id" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                <option value="">{{ __('Search customers…') }}</option>
                @foreach ($clients as $client)
                    <option value="{{ $client->id }}" @selected(old('client_id', $project->client_id) == $client->id)>{{ $client->display_name }}</option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-slate-400">{{ __('Optional. Link the project to a customer for easier filtering.') }}</p>
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Project manager') }}</label>
            <select name="project_manager_id" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                <option value="">{{ __('None') }}</option>
                @foreach ($employees as $employee)
                    <option value="{{ $employee->id }}" @selected(old('project_manager_id', $project->project_manager_id) == $employee->id)>{{ $employee->full_name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="grid sm:grid-cols-2 gap-5">
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Business location') }}</label>
            <select name="business_location_id" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                <option value="">{{ __('None') }}</option>
                @foreach ($businessLocations as $branch)
                    <option value="{{ $branch->id }}" @selected(old('business_location_id', $project->business_location_id) == $branch->id)>{{ $branch->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Cost centre') }}</label>
            <select name="cost_center_id" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                <option value="">{{ __('None') }}</option>
                @foreach ($costCenters as $costCenter)
                    <option value="{{ $costCenter->id }}" @selected(old('cost_center_id', $project->cost_center_id) == $costCenter->id)>{{ $costCenter->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="grid sm:grid-cols-2 gap-5">
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Site address') }}</label>
            <input type="text" name="site_address" value="{{ old('site_address', $project->site_address) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('City') }}</label>
            <input type="text" name="city" value="{{ old('city', $project->city) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
    </div>

    <div class="grid sm:grid-cols-2 gap-5">
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Start date') }}</label>
            <input type="date" name="start_date" value="{{ old('start_date', optional($project->start_date)->format('Y-m-d')) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('End date') }}</label>
            <input type="date" name="end_date" value="{{ old('end_date', optional($project->end_date)->format('Y-m-d')) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
    </div>

    <div class="border-t border-slate-100 pt-5">
        <h3 class="text-sm font-semibold text-slate-900 mb-4">{{ __('Billing & contract') }}</h3>
        <div class="grid sm:grid-cols-2 gap-5">
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Billing type') }}</label>
                <select name="billing_type" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                    @foreach (['no_billing' => __('No billing'), 'fixed_price' => __('Fixed price'), 'time_and_material' => __('Time & material'), 'milestones' => __('Milestones'), 'progress_retention' => __('Progress (with retention)'), 'recurring_amc' => __('Recurring (AMC)'), 'per_unit_sale' => __('Per-unit sale')] as $value => $label)
                        <option value="{{ $value }}" @selected(old('billing_type', $project->billing_type) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Default VAT rate') }}</label>
                <select name="default_tax_rate_id" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                    <option value="">{{ __('Company default') }}</option>
                    @foreach ($taxRates as $taxRate)
                        <option value="{{ $taxRate->id }}" @selected(old('default_tax_rate_id', $project->default_tax_rate_id) == $taxRate->id)>{{ $taxRate->name }} ({{ $taxRate->rate }}%)</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="grid sm:grid-cols-2 gap-5 mt-5">
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Contract value (SAR)') }}</label>
                <input type="number" step="0.01" min="0" name="contract_value" value="{{ old('contract_value', $project->contract_value) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                <p class="mt-1 text-xs text-slate-400">{{ __('The signed amount this project is billed against — used by milestone/progress billing.') }}</p>
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Target revenue (SAR)') }}</label>
                <input type="number" step="0.01" min="0" name="target_revenue" value="{{ old('target_revenue', $project->target_revenue) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                <p class="mt-1 text-xs text-slate-400">{{ __('Optional. Used to compare actuals against budget.') }}</p>
            </div>
        </div>
        <div class="grid sm:grid-cols-3 gap-5 mt-5">
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Retention %') }}</label>
                <input type="number" step="0.01" min="0" max="100" name="retention_percent" value="{{ old('retention_percent', $project->retention_percent) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Retention calculated on') }}</label>
                <select name="retention_calculated_on" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                    <option value="excluding_vat" @selected(old('retention_calculated_on', $project->retention_calculated_on) === 'excluding_vat')>{{ __('Excluding VAT') }}</option>
                    <option value="including_vat" @selected(old('retention_calculated_on', $project->retention_calculated_on) === 'including_vat')>{{ __('Including VAT') }}</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Advance recovery %') }}</label>
                <input type="number" step="0.01" min="0" max="100" name="advance_recovery_percent" value="{{ old('advance_recovery_percent', $project->advance_recovery_percent) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                <p class="mt-1 text-xs text-slate-400">{{ __('% of each invoice withheld to recover an advance payment.') }}</p>
            </div>
        </div>
        <div class="mt-5">
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Cost ceiling (SAR)') }}</label>
            <input type="number" step="0.01" min="0" name="cost_ceiling" value="{{ old('cost_ceiling', $project->cost_ceiling) }}" class="mt-1 w-full max-w-xs rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            <p class="mt-1 text-xs text-slate-400">{{ __('Optional. Used to compare actuals against budget.') }}</p>
        </div>
    </div>

    <div>
        <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Description') }}</label>
        <textarea name="description" rows="3" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">{{ old('description', $project->description) }}</textarea>
    </div>

    <div class="flex flex-col gap-3">
        <a href="{{ route('app.projects.index') }}" class="text-center rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Cancel') }}</a>
        <button type="submit" class="rounded-lg bg-brand-600 px-4 py-3 text-sm font-semibold text-white hover:bg-brand-700">{{ $project->exists ? __('Save changes') : __('Create project') }}</button>
    </div>
</form>
@endsection
