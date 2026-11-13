@extends('layouts.app')

@section('title', __('Log a Trip'))

@section('content')
<div class="max-w-2xl">
    <h1 class="text-xl font-bold text-slate-900 mb-1">{{ __('Log a Trip') }}</h1>
    <p class="text-sm text-slate-500 mb-6">{{ __('A day-by-day record of who used a machine or vehicle, where, and why.') }}</p>

    <form method="POST" action="{{ route('app.machinery.trips.store') }}" class="bg-white rounded-xl border border-slate-100 p-6 space-y-4">
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
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Date') }}</label>
                <input type="date" name="trip_date" value="{{ old('trip_date', now()->toDateString()) }}" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Operator') }}</label>
                <select name="operator_employee_id" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                    <option value="">{{ __('None') }}</option>
                    @foreach ($operators as $operator)
                        <option value="{{ $operator->id }}" @selected(old('operator_employee_id') == $operator->id)>{{ $operator->full_name }}</option>
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
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Purpose') }}</label>
            <input type="text" name="purpose" value="{{ old('purpose') }}" required placeholder="{{ __('e.g. Material pickup, Site transfer') }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            @error('purpose')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>

        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('From (optional)') }}</label>
                <input type="text" name="from_location" value="{{ old('from_location') }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('To (optional)') }}</label>
                <input type="text" name="to_location" value="{{ old('to_location') }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
        </div>

        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Notes') }}</label>
            <textarea name="notes" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">{{ old('notes') }}</textarea>
        </div>

        <button type="submit" class="rounded-lg bg-brand-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Log trip') }}</button>
    </form>
</div>
@endsection
