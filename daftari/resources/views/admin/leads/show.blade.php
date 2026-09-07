@extends('layouts.admin')

@section('title', $lead->name)

@section('content')
<div class="mb-4">
    <a href="{{ route('admin.leads.index') }}" class="text-sm font-semibold text-slate-500 hover:underline">← {{ __('Back to leads') }}</a>
</div>

<div class="flex flex-wrap items-start justify-between gap-3 mb-6">
    <div>
        <h2 class="text-lg font-semibold text-slate-900">{{ $lead->name }}</h2>
        <p class="text-sm text-slate-500">
            {{ $lead->email }}
            @if ($lead->phone) · {{ $lead->phone }} @endif
            @if ($lead->company_name) · {{ $lead->company_name }} @endif
            · {{ __('Received') }} {{ $lead->created_at->format('Y-m-d H:i') }}
        </p>
    </div>
    <div class="flex items-center gap-2">
        @if ($lead->industryLabel())
            <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-sm font-semibold text-slate-600">{{ $lead->industryLabel() }}</span>
        @endif
        <span class="inline-flex items-center rounded-full px-3 py-1 text-sm font-semibold {{ $lead->stageBadgeClasses() }}">{{ $lead->stageLabel() }}</span>
    </div>
</div>

<div class="grid lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-4">
        @if ($lead->message)
            <div class="bg-white rounded-xl border border-slate-100 p-5">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm font-semibold text-slate-800">{{ __('Original message') }}</span>
                    <span class="text-xs text-slate-400">{{ $lead->sourceLabel() }}</span>
                </div>
                <p class="text-sm text-slate-700 whitespace-pre-line">{{ $lead->message }}</p>
            </div>
        @endif

        @if ($lead->isLost() && $lead->lost_reason)
            <div class="rounded-xl border border-red-200 bg-red-50 p-5 text-sm text-red-700">
                <span class="font-semibold">{{ __('Lost reason:') }}</span> {{ $lead->lost_reason }}
            </div>
        @endif

        <form method="POST" action="{{ route('admin.leads.note', $lead) }}" class="bg-white rounded-xl border border-slate-100 p-5 space-y-3">
            @csrf
            <label class="block text-sm font-medium text-slate-700">{{ __('Add a note') }}</label>
            <textarea name="body" rows="3" required maxlength="10000" class="w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500"></textarea>
            <button type="submit" class="rounded-lg bg-brand-600 px-6 py-2.5 font-semibold text-white hover:bg-brand-700">{{ __('Add note') }}</button>
        </form>

        <div class="bg-white rounded-xl border border-slate-100">
            <div class="px-5 py-4 border-b border-slate-100">
                <h3 class="font-semibold text-slate-900">{{ __('Notes & activity') }}</h3>
            </div>
            @if ($timeline->isEmpty())
                <p class="px-5 py-6 text-sm text-slate-500">{{ __('No activity recorded yet.') }}</p>
            @else
                <ul class="divide-y divide-slate-50">
                    @foreach ($timeline as $entry)
                        <li class="px-5 py-3 {{ $entry['is_note'] ? '' : 'bg-slate-50/50' }}">
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-sm font-semibold text-slate-800">{{ $entry['author'] ?? __('System') }}</span>
                                <span class="text-xs text-slate-400">{{ $entry['at']->format('Y-m-d H:i') }}</span>
                            </div>
                            <p class="text-sm text-slate-600 whitespace-pre-line">{{ $entry['body'] }}</p>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    <div class="space-y-6">
        <div class="bg-white rounded-xl border border-slate-100 p-5 space-y-4">
            <h3 class="font-semibold text-slate-900">{{ __('Manage lead') }}</h3>

            <form method="POST" action="{{ route('admin.leads.status', $lead) }}" x-data="{ status: '{{ $lead->status }}' }" class="space-y-2">
                @csrf
                <label class="block text-xs font-medium text-slate-500">{{ __('Stage') }}</label>
                <select name="status" x-model="status" class="w-full rounded-lg border border-slate-200 text-sm">
                    @foreach (\App\Models\Lead::STAGES as $value)
                        <option value="{{ $value }}">{{ (new \App\Models\Lead(['status' => $value]))->stageLabel() }}</option>
                    @endforeach
                </select>
                <div x-show="status === 'lost'">
                    <input type="text" name="lost_reason" value="{{ $lead->lost_reason }}" placeholder="{{ __('Reason lost (required)') }}" class="w-full rounded-lg border border-slate-200 text-sm">
                </div>
                <button type="submit" class="w-full rounded-lg bg-slate-800 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-900">{{ __('Save') }}</button>
            </form>

            <form method="POST" action="{{ route('admin.leads.assign', $lead) }}" class="space-y-2 pt-2 border-t border-slate-100">
                @csrf
                <label class="block text-xs font-medium text-slate-500">{{ __('Assigned admin') }}</label>
                <div class="flex gap-2">
                    <select name="assigned_admin_id" class="flex-1 rounded-lg border border-slate-200 text-sm">
                        <option value="">{{ __('Unassigned') }}</option>
                        @foreach ($admins as $admin)
                            <option value="{{ $admin->id }}" @selected($lead->assigned_admin_id === $admin->id)>{{ $admin->name }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="rounded-lg bg-slate-800 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-900">{{ __('Save') }}</button>
                </div>
            </form>

            <form method="POST" action="{{ route('admin.leads.follow-up', $lead) }}" class="space-y-2 pt-2 border-t border-slate-100">
                @csrf
                <label class="block text-xs font-medium text-slate-500">{{ __('Demo date & time') }}</label>
                <input type="datetime-local" name="demo_at" value="{{ optional($lead->demo_at)->format('Y-m-d\TH:i') }}" class="w-full rounded-lg border border-slate-200 text-sm">
                <label class="block text-xs font-medium text-slate-500">{{ __('Next follow-up') }}</label>
                <input type="datetime-local" name="next_follow_up_at" value="{{ optional($lead->next_follow_up_at)->format('Y-m-d\TH:i') }}" class="w-full rounded-lg border border-slate-200 text-sm">
                <button type="submit" class="w-full rounded-lg bg-slate-800 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-900">{{ __('Save follow-up') }}</button>
            </form>

            <form method="POST" action="{{ route('admin.leads.link-company', $lead) }}" class="space-y-2 pt-2 border-t border-slate-100">
                @csrf
                <label class="block text-xs font-medium text-slate-500">{{ __('Linked company account') }}</label>
                <div class="flex gap-2">
                    <select name="converted_company_id" class="flex-1 rounded-lg border border-slate-200 text-sm">
                        <option value="">{{ __('Not linked') }}</option>
                        @foreach ($companies as $company)
                            <option value="{{ $company->id }}" @selected($lead->converted_company_id === $company->id)>{{ $company->name }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="rounded-lg bg-slate-800 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-900">{{ __('Save') }}</button>
                </div>
                @if ($lead->convertedCompany)
                    <a href="{{ route('admin.companies.show', $lead->convertedCompany) }}" class="block text-xs font-semibold text-brand-700 hover:underline">{{ __('View company account') }} →</a>
                @endif
            </form>

            <dl class="pt-2 border-t border-slate-100 space-y-1.5 text-sm">
                <div class="flex justify-between"><dt class="text-slate-500">{{ __('Source') }}</dt><dd class="font-medium">{{ $lead->sourceLabel() }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">{{ __('Received') }}</dt><dd class="font-medium">{{ $lead->created_at->format('Y-m-d H:i') }}</dd></div>
                @if ($lead->last_activity_at)
                    <div class="flex justify-between"><dt class="text-slate-500">{{ __('Last activity') }}</dt><dd class="font-medium">{{ $lead->last_activity_at->format('Y-m-d H:i') }}</dd></div>
                @endif
            </dl>
        </div>
    </div>
</div>
@endsection
