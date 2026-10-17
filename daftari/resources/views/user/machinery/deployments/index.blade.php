@extends('layouts.app')

@section('title', __('Project Deployments'))

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-xl font-bold text-slate-900">{{ __('Project Deployments') }}</h1>
        <p class="text-sm text-slate-500 mt-1">{{ __('Machinery deployed on your own projects — no client, no invoice.') }}</p>
    </div>
    <a href="{{ route('app.machinery.deployments.create') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('+ New deployment') }}</a>
</div>

<div class="bg-white rounded-xl border border-slate-100">
    @if ($deployments->isEmpty())
        <p class="px-6 py-8 text-sm text-slate-500">{{ __('No deployments yet.') }}</p>
    @else
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="px-6 py-3 font-medium">{{ __('Machine') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Project') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Period') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Status') }}</th>
                    <th class="px-6 py-3 font-medium"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($deployments as $deployment)
                    <tr class="border-b border-slate-50 last:border-0">
                        <td class="px-6 py-3 font-medium text-slate-900">
                            <a href="{{ route('app.machinery.assets.show', $deployment->machinery) }}" class="hover:underline">{{ $deployment->machinery->name }}</a>
                        </td>
                        <td class="px-6 py-3">{{ $deployment->project->name }}</td>
                        <td class="px-6 py-3 text-slate-500">{{ $deployment->start_date->format('Y-m-d') }} — {{ $deployment->end_date?->format('Y-m-d') ?? __('ongoing') }}</td>
                        <td class="px-6 py-3">
                            <span class="inline-block rounded-full {{ $deployment->status === 'active' ? 'bg-amber-50 text-amber-700' : 'bg-slate-100 text-slate-600' }} text-xs font-medium px-2.5 py-1">{{ ucfirst($deployment->status) }}</span>
                        </td>
                        <td class="px-6 py-3 text-right">
                            @if ($deployment->status === 'active')
                                <form method="POST" action="{{ route('app.machinery.deployments.end', $deployment) }}" onsubmit="return confirm('{{ __('End this deployment and mark the machine available?') }}')">
                                    @csrf
                                    <input type="hidden" name="end_date" value="{{ now()->toDateString() }}">
                                    <button type="submit" class="text-xs font-semibold text-slate-600 hover:underline">{{ __('End deployment') }}</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

<div class="mt-4">@include('partials.pagination', ['paginator' => $deployments])</div>
@endsection
