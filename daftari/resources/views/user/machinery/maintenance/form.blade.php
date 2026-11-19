@extends('layouts.app')

@section('title', __('Log Maintenance'))

@section('content')
<div class="max-w-2xl">
    <h1 class="text-xl font-bold text-slate-900 mb-1">{{ __('Log Maintenance') }}</h1>
    <p class="text-sm text-slate-500 mb-6">{{ __('Records the service and, if you set a next due date, schedules the reminder for it — replacing any reminder already sent for the old date.') }}</p>

    <form method="POST" action="{{ route('app.machinery.maintenance.store') }}" class="bg-white rounded-xl border border-slate-100 p-6 space-y-4">
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
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Service type') }}</label>
            <input type="text" name="service_type" value="{{ old('service_type') }}" required placeholder="{{ __('e.g. Oil change, Tyre replacement, General service') }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            @error('service_type')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>

        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Performed on') }}</label>
                <input type="date" name="performed_at" value="{{ old('performed_at', now()->toDateString()) }}" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Next due (optional)') }}</label>
                <input type="date" name="next_due_date" value="{{ old('next_due_date') }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                <p class="text-xs text-slate-400 mt-1">{{ __('Sets this machine\'s reminder — leave blank to just log history.') }}</p>
            </div>
        </div>

        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Real cost (optional)') }}</label>
            <select name="expense_id" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                <option value="">{{ __('No linked expense') }}</option>
                @foreach ($expenses as $expense)
                    <option value="{{ $expense->id }}" @selected(old('expense_id') == $expense->id)>{{ $expense->expense_date->format('Y-m-d') }} — {{ $expense->description }} ({{ number_format((float) $expense->gross_amount, 2) }} {{ __('SAR') }})</option>
                @endforeach
            </select>
            <p class="text-xs text-slate-400 mt-1">{{ __('Shows expenses already tagged to this machine — record the real cost as an Expense first, then link it here.') }}</p>
        </div>

        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Notes') }}</label>
            <textarea name="notes" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">{{ old('notes') }}</textarea>
        </div>

        <button type="submit" class="rounded-lg bg-brand-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Log maintenance') }}</button>
    </form>
</div>
@endsection
