@extends('layouts.admin')

@section('title', __('Sales Center'))

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-900">{{ __('Sales Center') }}</h1>
    <p class="text-sm text-slate-500 mt-1">{{ __('A working 90-day plan for getting real, paying customers — weekly goals against what the pipeline is actually doing, plus ready-to-use outreach tools.') }}</p>
</div>

@if (session('status'))
    <div class="mb-6 rounded-lg bg-emerald-50 border border-emerald-100 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
@endif

@include('admin.sales-center.partials.tabs')

<div class="grid grid-cols-2 gap-4 sm:gap-5 lg:grid-cols-5">
    @foreach ([
        ['label' => __('Open pipeline'), 'value' => number_format($stats['open_pipeline']), 'icon' => 'funnel', 'accent' => 'from-brand-500 to-emerald-500'],
        ['label' => __('New leads this month'), 'value' => number_format($stats['new_leads_this_month']), 'icon' => 'sparkle', 'accent' => 'from-sky-500 to-blue-500'],
        ['label' => __('Won, all time'), 'value' => number_format($stats['won_all_time']), 'icon' => 'trend-up', 'accent' => 'from-emerald-500 to-teal-500'],
        ['label' => __('Active accountant partners'), 'value' => number_format($stats['active_partners']), 'icon' => 'team', 'accent' => 'from-violet-500 to-purple-500'],
        ['label' => __('Revenue this month'), 'value' => 'SAR '.number_format($stats['revenue_this_month'], 0), 'icon' => 'billing', 'accent' => 'from-amber-500 to-orange-500'],
    ] as $card)
        <div class="rounded-2xl border border-slate-100 bg-white p-5 shadow-card">
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

<div class="mt-6 bg-white rounded-xl border border-slate-100 p-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="font-semibold text-slate-900">{{ __('Weekly plan') }}</h2>
            <p class="mt-1 text-sm text-slate-500">{{ __('Plan started :date — week :current of :total is current.', ['date' => $planStart->format('Y-m-d'), 'current' => $currentWeek, 'total' => \App\Models\SalesTarget::WEEKS]) }}</p>
        </div>
        <form method="POST" action="{{ route('admin.sales-center.restart') }}" class="flex items-end gap-2" onsubmit="return confirm('{{ __('Restart the plan? Week 1 will begin on the date you pick.') }}')">
            @csrf
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Restart plan from') }}</label>
                <input type="date" name="start_date" required value="{{ now()->toDateString() }}" class="mt-1 rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
            </div>
            <button type="submit" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">{{ __('Restart') }}</button>
        </form>
    </div>

    <form method="POST" action="{{ route('admin.sales-center.targets.update') }}" class="mt-5">
        @csrf
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs font-semibold uppercase text-slate-400 border-b border-slate-100">
                        <th class="py-2 pe-3">{{ __('Week') }}</th>
                        <th class="py-2 pe-3">{{ __('Dates') }}</th>
                        <th class="py-2 pe-3">{{ __('New leads (actual / target)') }}</th>
                        <th class="py-2 pe-3">{{ __('Demos (actual / target)') }}</th>
                        <th class="py-2 pe-3">{{ __('Won (actual / target)') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @foreach ($rows as $row)
                        @php $isCurrent = $row['target']->week_number === $currentWeek; @endphp
                        <tr class="{{ $isCurrent ? 'bg-brand-50/60' : '' }}">
                            <td class="py-2.5 pe-3 font-semibold text-slate-800">
                                {{ __('Week :n', ['n' => $row['target']->week_number]) }}
                                @if ($isCurrent)
                                    <span class="ms-1 rounded-full bg-brand-100 px-2 py-0.5 text-[10px] font-semibold text-brand-700 align-middle">{{ __('current') }}</span>
                                @endif
                            </td>
                            <td class="py-2.5 pe-3 text-slate-500 whitespace-nowrap">{{ $row['week_start']->format('M j') }} – {{ $row['week_end']->format('M j') }}</td>
                            <td class="py-2.5 pe-3">
                                <span class="{{ $row['new_leads_actual'] >= $row['target']->new_leads_target ? 'text-emerald-600 font-semibold' : 'text-slate-700' }}">{{ $row['new_leads_actual'] }}</span>
                                <span class="text-slate-300"> / </span>
                                <input type="number" min="0" name="targets[{{ $row['target']->week_number }}][new_leads_target]" value="{{ old('targets.'.$row['target']->week_number.'.new_leads_target', $row['target']->new_leads_target) }}" class="w-16 rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                            </td>
                            <td class="py-2.5 pe-3">
                                <span class="{{ $row['demos_actual'] >= $row['target']->demos_target ? 'text-emerald-600 font-semibold' : 'text-slate-700' }}">{{ $row['demos_actual'] }}</span>
                                <span class="text-slate-300"> / </span>
                                <input type="number" min="0" name="targets[{{ $row['target']->week_number }}][demos_target]" value="{{ old('targets.'.$row['target']->week_number.'.demos_target', $row['target']->demos_target) }}" class="w-16 rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                            </td>
                            <td class="py-2.5 pe-3">
                                <span class="{{ $row['won_actual'] >= $row['target']->won_target ? 'text-emerald-600 font-semibold' : 'text-slate-700' }}">{{ $row['won_actual'] }}</span>
                                <span class="text-slate-300"> / </span>
                                <input type="number" min="0" name="targets[{{ $row['target']->week_number }}][won_target]" value="{{ old('targets.'.$row['target']->week_number.'.won_target', $row['target']->won_target) }}" class="w-16 rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="mt-3 text-xs text-slate-400">{{ __('"Won" counts leads that reached Paid/Active status during that week, based on their last status update — a directional signal, not an accounting record.') }}</p>
        <button type="submit" class="mt-4 rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Save targets') }}</button>
    </form>
</div>
@endsection
