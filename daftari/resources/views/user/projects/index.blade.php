@extends('layouts.app')

@section('title', __('Projects'))

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h2 class="text-lg font-semibold text-slate-900">{{ __('Projects') }}</h2>
        <p class="text-sm text-slate-500 mt-1">{{ __('Track revenue, costs, and margin per project.') }}</p>
    </div>
    <a href="{{ route('app.projects.create') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">+ {{ __('New project') }}</a>
</div>

<form method="GET" class="flex items-center gap-2 mb-6 text-sm">
    @foreach (['' => __('All'), 'active' => __('Active'), 'on_hold' => __('On hold'), 'completed' => __('Completed'), 'cancelled' => __('Cancelled')] as $value => $label)
        <a href="{{ route('app.projects.index', array_filter(['status' => $value])) }}" class="rounded-lg px-3 py-1.5 {{ request('status', '') === $value ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100' }}">{{ $label }}</a>
    @endforeach
</form>

<div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
    @forelse ($projects as $project)
        @php
            $margin = $project->margin();
        @endphp
        <a href="{{ route('app.projects.show', $project) }}" class="block bg-white rounded-xl border border-slate-100 p-5 hover:border-brand-200">
            <div class="flex items-center justify-between">
                <span class="text-xs font-mono text-slate-400">{{ $project->code }}</span>
                <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold
                    {{ $project->status === 'active' ? 'bg-emerald-50 text-emerald-700' : ($project->status === 'completed' ? 'bg-slate-100 text-slate-600' : ($project->status === 'on_hold' ? 'bg-amber-50 text-amber-700' : 'bg-red-50 text-red-600')) }}">
                    {{ __(ucfirst(str_replace('_', ' ', $project->status))) }}
                </span>
            </div>
            <p class="mt-2 font-semibold text-slate-900">{{ $project->name }}</p>
            @if ($project->client)<p class="text-xs text-slate-400 mt-0.5">{{ $project->client->name }}</p>@endif
            <div class="mt-4 grid grid-cols-2 gap-2 text-xs">
                <div><p class="text-slate-400">{{ __('Revenue') }}</p><p class="font-semibold text-slate-800">{{ \App\Support\Money::format($project->revenue(), decimalsOverride: 0) }}</p></div>
                <div><p class="text-slate-400">{{ __('Margin') }}</p><p class="font-semibold {{ $margin >= 0 ? 'text-emerald-600' : 'text-red-600' }}">{{ \App\Support\Money::format($margin, decimalsOverride: 0) }}</p></div>
            </div>
        </a>
    @empty
        <div class="col-span-full bg-white rounded-xl border border-slate-100 px-6 py-16 text-center">
            <p class="text-sm text-slate-500 mb-4">{{ __('No projects yet.') }}</p>
            <a href="{{ route('app.projects.create') }}" class="inline-block rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">+ {{ __('New project') }}</a>
        </div>
    @endforelse
</div>

@include('partials.pagination', ['paginator' => $projects])
@endsection
