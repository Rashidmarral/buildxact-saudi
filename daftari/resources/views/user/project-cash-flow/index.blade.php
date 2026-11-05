@extends('layouts.app')

@section('title', __('Project Cash Flow'))

@section('content')
<div class="mb-6">
    <h2 class="text-lg font-semibold text-slate-900">{{ __('Project Cash Flow') }}</h2>
    <p class="text-sm text-slate-500 mt-1">{{ __('Pick a project or a bank account to see exactly how much cash it has received, paid out, and transferred — with a running balance and a downloadable PDF statement.') }}</p>
</div>

<div class="grid lg:grid-cols-2 gap-6">
    <div class="bg-white rounded-xl border border-slate-100 p-6">
        <h3 class="font-semibold text-slate-900 mb-1">{{ __('By project') }}</h3>
        <p class="text-xs text-slate-400 mb-4">{{ __('See total received, paid, and transferred for one project across every bank account.') }}</p>
        @if ($projects->isEmpty())
            <p class="text-sm text-slate-400 py-6 text-center">{{ __('No projects yet.') }}</p>
        @else
            <div class="divide-y divide-slate-50">
                @foreach ($projects as $project)
                    <a href="{{ route('app.project-cash-flow.show', $project) }}" class="flex items-center justify-between py-3 hover:bg-slate-50 -mx-2 px-2 rounded-lg">
                        <span class="font-medium text-slate-700">{{ $project->name }}</span>
                        <span class="text-slate-300">→</span>
                    </a>
                @endforeach
            </div>
        @endif
    </div>

    <div class="bg-white rounded-xl border border-slate-100 p-6">
        <h3 class="font-semibold text-slate-900 mb-1">{{ __('By bank account') }}</h3>
        <p class="text-xs text-slate-400 mb-4">{{ __('A chronological, running-balance statement for one account, across every project — including how much cash currently remains on hand for a Cash-type account (e.g. after a withdrawal).') }}</p>
        @if ($bankAccounts->isEmpty())
            <p class="text-sm text-slate-400 py-6 text-center">{{ __('No active bank/cash accounts yet.') }}</p>
        @else
            <div class="divide-y divide-slate-50">
                @foreach ($bankAccounts as $account)
                    <a href="{{ route('app.project-cash-flow.bank-account.show', $account) }}" class="flex items-center justify-between py-3 hover:bg-slate-50 -mx-2 px-2 rounded-lg">
                        <span class="flex items-center gap-2">
                            <span class="font-medium text-slate-700">{{ $account->name }}</span>
                            @if ($account->type === 'cash')
                                <span class="inline-block rounded-full bg-amber-50 text-amber-700 text-xs font-medium px-2 py-0.5">{{ __('Cash') }}</span>
                            @endif
                        </span>
                        <span class="flex items-center gap-3">
                            <span class="text-sm font-semibold text-slate-900 tabular-nums">{{ \App\Support\Money::format($account->currentBalance()) }}</span>
                            <span class="text-slate-300">→</span>
                        </span>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection
