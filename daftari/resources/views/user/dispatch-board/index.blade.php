@extends('layouts.app')

@section('title', __('Dispatch Board'))

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h2 class="text-lg font-semibold text-slate-900">{{ __('Dispatch Board') }}</h2>
        <p class="text-sm text-slate-500 mt-1">{{ __('Work orders scheduled this week, by technician.') }}</p>
    </div>
    <div class="flex items-center gap-2 text-sm">
        <a href="{{ route('app.dispatch-board.index', ['week' => $weekStart->copy()->subWeek()->toDateString()]) }}" class="rounded-lg border border-slate-200 px-3 py-1.5 text-slate-600 hover:border-slate-300">&larr; {{ __('Previous') }}</a>
        <span class="px-2 text-slate-500">{{ $weekStart->format('Y-m-d') }} &ndash; {{ $weekEnd->format('Y-m-d') }}</span>
        <a href="{{ route('app.dispatch-board.index', ['week' => $weekStart->copy()->addWeek()->toDateString()]) }}" class="rounded-lg border border-slate-200 px-3 py-1.5 text-slate-600 hover:border-slate-300">{{ __('Next') }} &rarr;</a>
    </div>
</div>

@if ($unassigned->isNotEmpty())
    <div class="bg-amber-50 border border-amber-100 rounded-xl p-4 mb-6">
        <p class="text-xs font-semibold uppercase text-amber-700 mb-2">{{ __('Unassigned work orders') }}</p>
        <div class="flex flex-wrap gap-2">
            @foreach ($unassigned as $workOrder)
                <span class="inline-block rounded-lg bg-white border border-amber-200 px-3 py-1.5 text-xs text-slate-700">
                    {{ $workOrder->work_order_number }} — {{ $workOrder->title }} ({{ $workOrder->project->name }})
                </span>
            @endforeach
        </div>
    </div>
@endif

<div class="overflow-x-auto">
    <table class="w-full text-sm border-separate border-spacing-0 min-w-[900px]">
        <thead>
            <tr>
                <th class="sticky start-0 bg-white px-3 py-2 text-left text-xs font-medium text-slate-500 border-b border-slate-100 w-36">{{ __('Technician') }}</th>
                @foreach ($days as $day)
                    <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 border-b border-slate-100 {{ $day->isToday() ? 'bg-brand-50' : '' }}">{{ $day->translatedFormat('D j M') }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($technicians as $technician)
                <tr class="border-b border-slate-50">
                    <td class="sticky start-0 bg-white px-3 py-3 font-medium text-slate-900 align-top">{{ $technician->full_name }}</td>
                    @foreach ($days as $day)
                        @php($dayOrders = $workOrders->filter(fn ($wo) => $wo->technician_employee_id === $technician->id && $wo->scheduled_at?->isSameDay($day)))
                        <td class="px-3 py-3 align-top {{ $day->isToday() ? 'bg-brand-50/40' : '' }}">
                            @foreach ($dayOrders as $workOrder)
                                <div class="rounded-lg border border-slate-200 bg-white p-2 mb-2 text-xs">
                                    <p class="font-semibold text-slate-900">{{ $workOrder->title }}</p>
                                    <p class="text-slate-400">{{ $workOrder->project->name }} · {{ $workOrder->scheduled_at->format('H:i') }}</p>
                                    <span class="inline-block mt-1 rounded-full px-2 py-0.5 text-[10px] font-medium {{ $workOrder->priority === 'urgent' ? 'bg-red-50 text-red-700' : 'bg-slate-100 text-slate-600' }}">{{ ucfirst($workOrder->priority) }}</span>
                                    <details class="mt-1">
                                        <summary class="text-brand-700 cursor-pointer">{{ __('Reschedule') }}</summary>
                                        <form method="POST" action="{{ route('app.projects.work-orders.update', [$workOrder->project_id, $workOrder]) }}" class="mt-2 space-y-1">
                                            @csrf @method('PUT')
                                            <input type="datetime-local" name="scheduled_at" value="{{ $workOrder->scheduled_at->format('Y-m-d\TH:i') }}" class="w-full rounded border border-slate-200 text-xs">
                                            <select name="technician_employee_id" class="w-full rounded border border-slate-200 text-xs">
                                                <option value="">{{ __('Unassigned') }}</option>
                                                @foreach ($technicians as $t)
                                                    <option value="{{ $t->id }}" @selected($t->id === $technician->id)>{{ $t->full_name }}</option>
                                                @endforeach
                                            </select>
                                            <input type="hidden" name="status" value="{{ $workOrder->status }}">
                                            <button type="submit" class="w-full rounded bg-brand-600 text-white text-xs py-1">{{ __('Save') }}</button>
                                        </form>
                                    </details>
                                </div>
                            @endforeach
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
