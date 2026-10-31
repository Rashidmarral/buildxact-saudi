@extends('layouts.app')

@section('title', __('Pipeline'))

@section('content')
<div class="flex items-center justify-between mb-6">
    <p class="text-sm text-slate-500">{{ __('Prospects moving toward becoming a paying client. Set a pipeline stage on any client to add it here.') }}</p>
    <a href="{{ route('app.clients.index') }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('All clients') }}</a>
</div>

@php($stageLabels = [
    'prospect' => __('Prospect'),
    'contacted' => __('Contacted'),
    'qualified' => __('Qualified'),
    'negotiating' => __('Negotiating'),
    'won' => __('Won'),
    'lost' => __('Lost'),
])

<div class="grid grid-flow-col auto-cols-[260px] gap-4 overflow-x-auto pb-2">
    @foreach ($stages as $stage)
        @php($column = $clientsByStage->get($stage, collect()))
        <div class="flex flex-col rounded-xl border border-slate-100 bg-slate-50/60">
            <div class="flex items-center justify-between px-4 py-3 border-b border-slate-100">
                <span class="text-sm font-semibold text-slate-700">{{ $stageLabels[$stage] }}</span>
                <span class="rounded-full bg-white px-2 py-0.5 text-xs font-semibold text-slate-500 border border-slate-200">{{ $column->count() }}</span>
            </div>
            <div class="flex-1 space-y-2 p-3">
                @forelse ($column as $client)
                    <div class="rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
                        <a href="{{ route('app.clients.edit', $client) }}" class="block text-sm font-medium text-slate-800 hover:text-brand-700 hover:underline">{{ $client->display_name }}</a>
                        @if ($client->lead_source)
                            <p class="mt-0.5 text-xs text-slate-400">{{ $client->lead_source }}</p>
                        @endif
                        @if ($client->next_follow_up_date)
                            <p class="mt-1 text-xs {{ $client->next_follow_up_date->isPast() ? 'text-red-600 font-medium' : 'text-slate-500' }}">
                                {{ __('Follow up') }}: {{ $client->next_follow_up_date->format('Y-m-d') }}
                            </p>
                        @endif
                        @if ($stage === 'lost' && $client->lost_reason)
                            <p class="mt-1 text-xs text-slate-400">{{ $client->lost_reason }}</p>
                        @endif

                        @if (! in_array($stage, ['won', 'lost']))
                            <form method="POST" action="{{ route('app.clients.advance-stage', $client) }}" class="mt-2 flex items-center gap-1.5" x-data="{ nextStage: '' }">
                                @csrf
                                <select name="pipeline_stage" x-model="nextStage" required class="flex-1 rounded-md border border-slate-200 text-xs focus:border-brand-500 focus:ring-brand-500">
                                    <option value="" disabled selected>{{ __('Move to…') }}</option>
                                    @foreach ($stages as $target)
                                        @continue($target === $stage)
                                        <option value="{{ $target }}">{{ $stageLabels[$target] }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="rounded-md bg-brand-600 px-2 py-1 text-xs font-semibold text-white hover:bg-brand-700">{{ __('Go') }}</button>
                            </form>
                        @else
                            <p class="mt-2 text-xs text-slate-300">{{ __('Final stage') }}</p>
                        @endif
                    </div>
                @empty
                    <p class="px-1 py-6 text-center text-xs text-slate-400">{{ __('Nothing here.') }}</p>
                @endforelse
            </div>
        </div>
    @endforeach
</div>
@endsection
