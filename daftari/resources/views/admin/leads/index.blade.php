@extends('layouts.admin')

@section('title', __('Sales & CRM'))

@section('content')
<div class="grid grid-cols-2 gap-4 sm:gap-5 lg:grid-cols-5">
    @foreach ([
        ['label' => __('Open pipeline'), 'value' => number_format($stats['open']), 'icon' => 'funnel', 'accent' => 'from-sky-500 to-blue-500'],
        ['label' => __('New this month'), 'value' => number_format($stats['new_this_month']), 'icon' => 'sparkle', 'accent' => 'from-violet-500 to-purple-500'],
        ['label' => __('Demos scheduled'), 'value' => number_format($stats['demos_scheduled']), 'icon' => 'clock', 'accent' => 'from-amber-500 to-orange-500'],
        ['label' => __('Won'), 'value' => number_format($stats['won']), 'icon' => 'check-circle', 'accent' => 'from-emerald-500 to-teal-500'],
        ['label' => __('Conversion rate'), 'value' => $stats['conversion_rate'] !== null ? $stats['conversion_rate'].'%' : '—', 'icon' => 'trend-up', 'accent' => 'from-slate-500 to-slate-600'],
    ] as $card)
        <div class="card-hover rounded-2xl border border-slate-100 bg-white p-5 shadow-card">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-slate-500">{{ $card['label'] }}</span>
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br {{ $card['accent'] }} text-white">
                    @include('partials.icon', ['name' => $card['icon'], 'class' => 'h-4 w-4'])
                </span>
            </div>
            <div class="mt-3 text-2xl font-bold text-slate-900">{{ $card['value'] }}</div>
        </div>
    @endforeach
</div>

<form method="GET" class="mt-6 mb-6 bg-white rounded-xl border border-slate-100 p-4 space-y-3">
    <div class="flex flex-wrap items-end gap-3">
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Search') }}</label>
            <input type="text" name="q" value="{{ request('q') }}" placeholder="{{ __('Name, email, or company...') }}" class="rounded-lg border border-slate-200 text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Stage') }}</label>
            <select name="status" class="rounded-lg border border-slate-200 text-sm">
                <option value="">{{ __('All stages') }}</option>
                @foreach (\App\Models\Lead::STAGES as $value)
                    <option value="{{ $value }}" @selected(request('status') === $value)>{{ (new \App\Models\Lead(['status' => $value]))->stageLabel() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Source') }}</label>
            <select name="source" class="rounded-lg border border-slate-200 text-sm">
                <option value="">{{ __('All sources') }}</option>
                @foreach (\App\Models\Lead::SOURCES as $value)
                    <option value="{{ $value }}" @selected(request('source') === $value)>{{ (new \App\Models\Lead(['source' => $value]))->sourceLabel() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Industry') }}</label>
            <select name="industry" class="rounded-lg border border-slate-200 text-sm">
                <option value="">{{ __('All industries') }}</option>
                @foreach (\App\Models\Lead::INDUSTRIES as $value)
                    <option value="{{ $value }}" @selected(request('industry') === $value)>{{ (new \App\Models\Lead(['industry' => $value]))->industryLabel() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Assigned to') }}</label>
            <select name="assigned" class="rounded-lg border border-slate-200 text-sm">
                <option value="">{{ __('Anyone') }}</option>
                <option value="unassigned" @selected(request('assigned') === 'unassigned')>{{ __('Unassigned') }}</option>
                @foreach ($admins as $admin)
                    <option value="{{ $admin->id }}" @selected((string) request('assigned') === (string) $admin->id)>{{ $admin->name }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Filter') }}</button>
        @if (request()->anyFilled(['q', 'status', 'source', 'industry', 'assigned']))
            <a href="{{ route('admin.leads.index') }}" class="text-sm font-semibold text-slate-500 hover:underline">{{ __('Clear') }}</a>
        @endif
    </div>
</form>

<div class="bg-white rounded-xl border border-slate-100 overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-slate-500 border-b border-slate-100 whitespace-nowrap">
                <th class="px-6 py-3 font-medium">{{ __('Name') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Company') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Industry') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Source') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Stage') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Assigned admin') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Received') }}</th>
                <th class="px-6 py-3"></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($leads as $lead)
                <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50">
                    <td class="px-6 py-3">
                        <div class="font-medium text-slate-800">{{ $lead->name }}</div>
                        <div class="text-xs text-slate-400">{{ $lead->email }}</div>
                    </td>
                    <td class="px-4 py-3">{{ $lead->company_name ?? '—' }}</td>
                    <td class="px-4 py-3">{{ $lead->industryLabel() ?? '—' }}</td>
                    <td class="px-4 py-3 text-slate-500">{{ $lead->sourceLabel() }}</td>
                    <td class="px-4 py-3"><span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold {{ $lead->stageBadgeClasses() }}">{{ $lead->stageLabel() }}</span></td>
                    <td class="px-4 py-3">{{ $lead->assignedAdmin?->name ?? __('Unassigned') }}</td>
                    <td class="px-4 py-3 text-slate-500">{{ $lead->created_at->diffForHumans() }}</td>
                    <td class="px-6 py-3 text-right">
                        <a href="{{ route('admin.leads.show', $lead) }}" class="text-brand-700 hover:underline">{{ __('View') }}</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="px-6 py-8 text-center text-slate-400">{{ __('No leads match these filters.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $leads->links() }}</div>
@endsection
