@extends('layouts.app')

@section('title', __('Activity log'))

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-900">{{ __('Activity log') }}</h1>
    <p class="text-sm text-slate-500 mt-1">{{ __('A record of who did what across your company account.') }}</p>
</div>

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

<div class="mt-4">{{ $logs->links() }}</div>
@endsection
