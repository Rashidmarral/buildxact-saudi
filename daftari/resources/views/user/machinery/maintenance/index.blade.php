@extends('layouts.app')

@section('title', __('Maintenance'))

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <div>
        <h1 class="text-xl font-bold text-slate-900">{{ __('Maintenance') }}</h1>
        <p class="text-sm text-slate-500 mt-1">{{ __('Service history and the next due date each machine is reminded against 30 days ahead.') }}</p>
    </div>
    <a href="{{ route('app.machinery.maintenance.create') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('+ Log maintenance') }}</a>
</div>

<x-list-toolbar>
<div class="bg-white rounded-xl border border-slate-100 mb-6">
    <div class="px-6 py-4 border-b border-slate-100">
        <h2 class="font-semibold text-slate-900 text-sm">{{ __('Upcoming / overdue') }}</h2>
    </div>
    @if ($upcoming->isEmpty())
        <p class="px-6 py-6 text-sm text-slate-500">{{ __('No machine has a maintenance date scheduled.') }}</p>
    @else
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="px-6 py-3 font-medium">{{ __('Machine') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Next due') }}</th>
                    <th class="px-6 py-3 font-medium"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($upcoming as $asset)
                    <tr class="border-b border-slate-50 last:border-0">
                        <td class="px-6 py-3 font-medium text-slate-900">
                            <a href="{{ route('app.machinery.assets.show', $asset) }}" class="hover:underline">{{ $asset->name }} ({{ $asset->asset_code }})</a>
                        </td>
                        <td class="px-6 py-3 text-slate-500">{{ $asset->next_maintenance_due_date->format('Y-m-d') }}</td>
                        <td class="px-6 py-3">
                            @if ($asset->isMaintenanceOverdue())
                                <span class="inline-block rounded-full bg-red-50 text-red-700 text-xs font-medium px-2.5 py-1">{{ __('Overdue') }}</span>
                            @else
                                <span class="inline-block rounded-full bg-amber-50 text-amber-700 text-xs font-medium px-2.5 py-1">{{ __('Scheduled') }}</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
</x-list-toolbar>

<x-list-toolbar>
<div class="bg-white rounded-xl border border-slate-100">
    <div class="px-6 py-4 border-b border-slate-100">
        <h2 class="font-semibold text-slate-900 text-sm">{{ __('Service history') }}</h2>
    </div>
    @if ($logs->isEmpty())
        <p class="px-6 py-8 text-sm text-slate-500">{{ __('No maintenance logged yet.') }}</p>
    @else
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="px-6 py-3 font-medium">{{ __('Machine') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Service') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Performed') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Next due') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Cost') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($logs as $log)
                    <tr class="border-b border-slate-50 last:border-0">
                        <td class="px-6 py-3 font-medium text-slate-900">
                            <a href="{{ route('app.machinery.assets.show', $log->machinery) }}" class="hover:underline">{{ $log->machinery->name }}</a>
                        </td>
                        <td class="px-6 py-3">{{ $log->service_type }}</td>
                        <td class="px-6 py-3 text-slate-500">{{ $log->performed_at->format('Y-m-d') }}</td>
                        <td class="px-6 py-3 text-slate-500">{{ $log->next_due_date?->format('Y-m-d') ?? '—' }}</td>
                        <td class="px-6 py-3 text-slate-500">
                            @if ($log->expense)
                                <a href="{{ route('app.expenses.edit', $log->expense) }}" class="hover:underline">{{ number_format((float) $log->expense->gross_amount, 2) }} {{ __('SAR') }}</a>
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
</x-list-toolbar>

<div class="mt-4">@include('partials.pagination', ['paginator' => $logs])</div>
@endsection
