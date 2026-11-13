@extends('layouts.app')

@section('title', __('Activity log'))

@section('content')
<div class="flex items-start justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">{{ __('Activity log') }}</h1>
        <p class="text-sm text-slate-500 mt-1">{{ __('A record of who did what across your company account.') }}</p>
    </div>
    <a href="{{ route('app.activity.index', array_merge(request()->query(), ['export' => 'csv'])) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300 shrink-0">{{ __('Download CSV') }}</a>
</div>

<form method="GET" class="bg-white rounded-xl border border-slate-100 p-4 mb-6 flex flex-wrap items-center gap-3">
    <select name="user_id" class="rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        <option value="">{{ __('All users') }}</option>
        @foreach ($users as $user)
            <option value="{{ $user->id }}" @selected((string) request('user_id') === (string) $user->id)>{{ $user->name }}</option>
        @endforeach
    </select>
    <select name="action" class="rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        <option value="">{{ __('All actions') }}</option>
        @foreach ($actions as $action)
            <option value="{{ $action }}" @selected(request('action') === $action)>{{ $action }}</option>
        @endforeach
    </select>
    <input type="date" name="from" value="{{ request('from') }}" class="rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
    <span class="text-slate-400 text-sm">{{ __('to') }}</span>
    <input type="date" name="to" value="{{ request('to') }}" class="rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
    <button type="submit" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Filter') }}</button>
    @if (request()->anyFilled(['user_id', 'action', 'from', 'to']))
        <a href="{{ route('app.activity.index') }}" class="text-sm font-semibold text-brand-700 hover:underline">{{ __('Clear') }}</a>
    @endif
</form>

<x-list-toolbar>
<div class="bg-white rounded-xl border border-slate-100">
    @if ($logs->isEmpty())
        <p class="px-6 py-8 text-sm text-slate-500">{{ __('No activity recorded yet.') }}</p>
    @else
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="px-6 py-3 font-medium">{{ __('When') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('User') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Action') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Details') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($logs as $log)
                    <tr class="border-b border-slate-50 last:border-0">
                        <td class="px-6 py-3 text-slate-500">{{ $log->created_at->format('Y-m-d H:i') }}</td>
                        <td class="px-6 py-3">{{ $log->admin?->name ?? __('System') }}</td>
                        <td class="px-6 py-3"><code class="text-xs text-slate-500">{{ $log->action }}</code></td>
                        <td class="px-6 py-3 text-slate-700">{{ $log->description ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
</x-list-toolbar>

<div class="mt-4">@include('partials.pagination', ['paginator' => $logs])</div>
@endsection
