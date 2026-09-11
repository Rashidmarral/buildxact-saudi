@extends('layouts.app')

@section('title', __('Accounting Health'))

@section('content')
<div class="flex items-start justify-between mb-6">
    <div>
        <h2 class="text-lg font-semibold text-slate-900">{{ __('Accounting Health') }}</h2>
        <p class="text-sm text-slate-500 mt-1">{{ __('A simple answer to: is my accounting working, and do I need to do anything?') }}</p>
    </div>
    <a href="{{ route('app.accounting-health.index') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Refresh') }}</a>
</div>

<div class="bg-white rounded-xl border border-slate-100 p-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div class="flex items-start gap-3">
        @if ($issueCount === 0)
            <span class="inline-flex shrink-0 items-center rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">{{ __('ALL CLEAR') }}</span>
            <div>
                <p class="font-medium text-slate-800">{{ __('Everything checked out — no issues found.') }}</p>
                <p class="text-xs text-slate-400 mt-1">{{ __('Checked at :time', ['time' => $checkedAt->format('Y-m-d H:i:s')]) }}</p>
            </div>
        @else
            <span class="inline-flex shrink-0 items-center rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700">{{ __('NEEDS REVIEW') }}</span>
            <div>
                <p class="font-medium text-slate-800">{{ __(':count items need your attention.', ['count' => $issueCount]) }}</p>
                <p class="text-xs text-slate-400 mt-1">{{ __('Checked at :time', ['time' => $checkedAt->format('Y-m-d H:i:s')]) }}</p>
            </div>
        @endif
    </div>
    <div class="rounded-lg border border-slate-100 bg-slate-50 px-4 py-2 text-center">
        <p class="text-xs text-slate-500">{{ __('Issues requiring attention') }}</p>
        <p class="text-2xl font-semibold text-slate-900">{{ $issueCount }}</p>
    </div>
</div>

<h3 class="text-sm font-semibold text-slate-700 mb-3">{{ __('Health Areas') }}</h3>
<p class="text-xs text-slate-400 mb-4">{{ __('Three plain-language areas summarize the detailed accounting checks.') }}</p>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
    @foreach ([
        ['label' => __('Accounting Setup'), 'desc' => __('Required semantic and payment account mappings.'), 'issues' => $setupIssues, 'route' => route('app.accounts.index'), 'action' => __('Configure Accounting')],
        ['label' => __('Financial Records'), 'desc' => __('Journal integrity, balances, and account status.'), 'issues' => $financialIssues, 'route' => route('app.reports.trial-balance'), 'action' => __('View Trial Balance')],
        ['label' => __('Transaction Processing'), 'desc' => __('Documents that should have an accounting entry.'), 'issues' => $transactionIssues, 'route' => route('app.journals.index'), 'action' => __('View Journals')],
    ] as $area)
        <div class="bg-white rounded-xl border border-slate-100 p-5">
            <div class="flex items-center justify-between mb-2">
                <p class="font-semibold text-slate-800">{{ $area['label'] }}</p>
                @if (count($area['issues']) === 0)
                    <span class="inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-700">{{ __('OK') }}</span>
                @else
                    <span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-700">{{ __('NEEDS REVIEW') }}</span>
                @endif
            </div>
            <p class="text-xs text-slate-500 mb-4">{{ $area['desc'] }}</p>
            <a href="{{ $area['route'] }}" class="inline-flex rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:border-slate-300">{{ $area['action'] }}</a>
        </div>
    @endforeach
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2">
        <h3 class="text-sm font-semibold text-slate-700 mb-3">{{ __('Issues Requiring Attention') }}</h3>
        <div class="space-y-3">
            @forelse (collect($setupIssues)->concat($financialIssues)->concat($transactionIssues) as $issue)
                <div class="bg-white rounded-xl border border-slate-100 p-4">
                    <div class="flex items-start justify-between gap-4">
                        <span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-700 shrink-0">{{ __('NEEDS REVIEW') }}</span>
                        @if ($issue['action_route'])
                            <a href="{{ $issue['action_route'] }}" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:border-slate-300 shrink-0">{{ $issue['action_label'] }}</a>
                        @endif
                    </div>
                    <p class="font-medium text-slate-800 mt-2">{{ $issue['title'] }}</p>
                    <p class="text-xs text-slate-500 mt-1">{{ $issue['detail'] }}</p>
                    <p class="text-[11px] text-slate-400 mt-2">{{ __('Technical label: :label', ['label' => $issue['technical_label']]) }}</p>
                </div>
            @empty
                <div class="bg-white rounded-xl border border-slate-100 p-8 text-center text-sm text-slate-400">{{ __('No issues found.') }}</div>
            @endforelse
        </div>
    </div>

    <div>
        <h3 class="text-sm font-semibold text-slate-700 mb-3">{{ __('Recent Activity') }}</h3>
        <div class="bg-white rounded-xl border border-slate-100 p-5">
            <ul class="space-y-4">
                @forelse ($recentActivity as $entry)
                    <li class="flex gap-3">
                        <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-brand-500"></span>
                        <div>
                            <p class="text-sm font-medium text-slate-800">{{ $entry->description ?: $entry->action }}</p>
                            <p class="text-xs text-slate-400">{{ $entry->created_at?->format('Y-m-d H:i:s') }}</p>
                        </div>
                    </li>
                @empty
                    <li class="text-sm text-slate-400">{{ __('No recent accounting activity.') }}</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
@endsection
