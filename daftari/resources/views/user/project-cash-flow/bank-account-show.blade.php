@extends('layouts.app')

@section('title', $bankAccount->name)

@section('content')
<div class="flex flex-wrap items-start justify-between gap-3 mb-6">
    <div>
        <a href="{{ route('app.project-cash-flow.index') }}" class="text-sm text-slate-400 hover:text-slate-600">{{ __('← Project Cash Flow') }}</a>
        <h2 class="text-lg font-semibold text-slate-900 mt-1">{{ $bankAccount->name }}</h2>
        <p class="text-sm text-slate-500 mt-1">{{ __('A chronological statement of every receipt, payment, and transfer through this account, with a running balance.') }}</p>
    </div>
    <div class="flex items-center gap-2">
        <form method="GET" class="flex items-center gap-2">
            <select name="project_id" onchange="this.form.submit()" class="rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                <option value="">{{ __('All projects') }}</option>
                @foreach ($projects as $project)
                    <option value="{{ $project->id }}" @selected($selectedProjectId == $project->id)>{{ $project->name }}</option>
                @endforeach
            </select>
        </form>
        <a href="{{ route('app.project-cash-flow.bank-account.pdf', array_merge(['bankAccount' => $bankAccount], request()->only('project_id'))) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download PDF') }}</a>
    </div>
</div>

@include('user.project-cash-flow.partials.ledger-table')
@endsection
