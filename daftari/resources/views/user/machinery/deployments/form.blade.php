@extends('layouts.app')

@section('title', __('New Deployment'))

@section('content')
<div class="max-w-2xl">
    <h1 class="text-xl font-bold text-slate-900 mb-1">{{ __('Deploy Machine on Project') }}</h1>
    <p class="text-sm text-slate-500 mb-6">{{ __('Tracks which project this machine is on. The real cost to the project is whatever fuel/maintenance/wage expenses you tag to both the project and this machine — not the internal rate below, which is only an informational estimate.') }}</p>

    <form method="POST" action="{{ route('app.machinery.deployments.store') }}" class="bg-white rounded-xl border border-slate-100 p-6 space-y-4">
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

        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Project') }}</label>
            <select name="project_id" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                <option value="">{{ __('Select a project') }}</option>
                @foreach ($projects as $project)
                    <option value="{{ $project->id }}" @selected(old('project_id') == $project->id)>{{ $project->name }}</option>
                @endforeach
            </select>
            @error('project_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>

        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Start date') }}</label>
                <input type="date" name="start_date" value="{{ old('start_date', $deployment->start_date) }}" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('End date (optional)') }}</label>
                <input type="date" name="end_date" value="{{ old('end_date') }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
        </div>

        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Internal daily rate (optional)') }}</label>
                <input type="number" step="0.01" min="0" name="internal_daily_rate" value="{{ old('internal_daily_rate') }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                <p class="text-xs text-slate-400 mt-1">{{ __('Informational only — what this machine would earn if rented instead.') }}</p>
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Operator') }}</label>
                <select name="operator_employee_id" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                    <option value="">{{ __('None') }}</option>
                    @foreach ($employees as $employee)
                        <option value="{{ $employee->id }}" @selected(old('operator_employee_id') == $employee->id)>{{ $employee->full_name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Notes') }}</label>
            <textarea name="notes" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">{{ old('notes') }}</textarea>
        </div>

        <button type="submit" class="rounded-lg bg-brand-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Deploy machine') }}</button>
    </form>
</div>
@endsection
