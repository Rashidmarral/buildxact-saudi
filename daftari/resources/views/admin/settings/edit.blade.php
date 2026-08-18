@extends('layouts.admin')

@section('title', __('Platform settings'))

@section('content')
<form method="POST" action="{{ route('admin.settings.update') }}" class="max-w-2xl space-y-6">
    @csrf

    <div class="bg-white rounded-xl border border-slate-100 p-6">
        <h3 class="font-semibold text-slate-900 mb-1">{{ __('Signups') }}</h3>
        <p class="text-sm text-slate-500 mb-4">{{ __('Applies to every new company that registers from here on — existing trials keep their original end date.') }}</p>
        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Trial length (days)') }}</label>
                <input type="number" name="trial_days" min="1" max="365" required value="{{ old('trial_days', $settings['trial_days']) }}" class="w-full rounded-lg border border-slate-200 text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Support email shown to customers') }}</label>
                <input type="email" name="support_email" required value="{{ old('support_email', $settings['support_email']) }}" class="w-full rounded-lg border border-slate-200 text-sm">
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-100 p-6">
        <h3 class="font-semibold text-slate-900 mb-1">{{ __('Maintenance mode') }}</h3>
        <p class="text-sm text-slate-500 mb-4">{{ __('While on, everyone except super admins sees a maintenance page instead of the app.') }}</p>
        <label class="flex items-center gap-2 text-sm text-slate-700">
            <input type="checkbox" name="maintenance_mode" value="1" @checked($settings['maintenance_mode']) class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
            {{ __('Put the platform into maintenance mode') }}
        </label>
        <div class="mt-3">
            <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Message shown on the maintenance page (optional)') }}</label>
            <textarea name="maintenance_message" rows="2" class="w-full rounded-lg border border-slate-200 text-sm">{{ old('maintenance_message', $settings['maintenance_message']) }}</textarea>
        </div>
    </div>

    <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Save settings') }}</button>
</form>
@endsection
