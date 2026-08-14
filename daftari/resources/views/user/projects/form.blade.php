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
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Status') }}</label>
            <select name="status" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                @foreach (['active' => __('Active'), 'on_hold' => __('On hold'), 'completed' => __('Completed'), 'cancelled' => __('Cancelled')] as $value => $label)
                    <option value="{{ $value }}" @selected(old('status', $project->status) === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Customer') }}</label>
            <select name="client_id" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                <option value="">{{ __('Search customers…') }}</option>
                @foreach ($clients as $client)
                    <option value="{{ $client->id }}" @selected(old('client_id', $project->client_id) == $client->id)>{{ $client->name }}</option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-slate-400">{{ __('Optional. Link the project to a customer for easier filtering.') }}</p>
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

    <div class="grid sm:grid-cols-2 gap-5">
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Target revenue (SAR)') }}</label>
            <input type="number" step="0.01" min="0" name="target_revenue" value="{{ old('target_revenue', $project->target_revenue) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            <p class="mt-1 text-xs text-slate-400">{{ __('Optional. Used to compare actuals against budget.') }}</p>
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Cost ceiling (SAR)') }}</label>
            <input type="number" step="0.01" min="0" name="cost_ceiling" value="{{ old('cost_ceiling', $project->cost_ceiling) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            <p class="mt-1 text-xs text-slate-400">{{ __('Optional. Used to compare actuals against budget.') }}</p>
        </div>
    </div>

    <div class="flex flex-col gap-3">
        <a href="{{ route('app.projects.index') }}" class="text-center rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Cancel') }}</a>
        <button type="submit" class="rounded-lg bg-brand-600 px-4 py-3 text-sm font-semibold text-white hover:bg-brand-700">{{ $project->exists ? __('Save changes') : __('Create project') }}</button>
    </div>
</form>
@endsection
