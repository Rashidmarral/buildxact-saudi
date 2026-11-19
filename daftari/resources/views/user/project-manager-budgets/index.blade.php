@extends('layouts.app')

@section('title', __('Project Manager Budgets'))

@section('content')
<div class="flex items-start justify-between mb-6">
    <div>
        <h2 class="text-lg font-semibold text-slate-900">{{ __('Project Manager Budgets') }}</h2>
        <p class="text-sm text-slate-500 mt-1">{{ __('Cash advanced to your own employees — a project manager or site supervisor managing a work package themselves — tracked with the same budget, breakdown, and voucher linking a subcontractor gets, kept separate from external subcontractors.') }}</p>
    </div>
    <a href="{{ route('app.project-manager-budgets.create') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('New budget') }}</a>
</div>

<form method="GET" class="bg-white rounded-xl border border-slate-100 p-4 mb-6 flex flex-wrap items-center gap-3">
    <select name="project_id" onchange="this.form.submit()" class="rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500 min-w-[14rem]">
        <option value="">{{ __('All projects') }}</option>
        @foreach ($projects as $p)<option value="{{ $p->id }}" @selected(request('project_id') == $p->id)>{{ $p->name }}</option>@endforeach
    </select>
    <select name="employee_id" onchange="this.form.submit()" class="rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500 min-w-[14rem]">
        <option value="">{{ __('All project managers') }}</option>
        @foreach ($employees as $e)<option value="{{ $e->id }}" @selected(request('employee_id') == $e->id)>{{ $e->full_name }}</option>@endforeach
    </select>
    <select name="status" onchange="this.form.submit()" class="rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        <option value="">{{ __('Any status') }}</option>
        <option value="active" @selected(request('status') === 'active')>{{ __('Active') }}</option>
        <option value="completed" @selected(request('status') === 'completed')>{{ __('Completed') }}</option>
        <option value="cancelled" @selected(request('status') === 'cancelled')>{{ __('Cancelled') }}</option>
    </select>
</form>

<x-list-toolbar>
<div class="bg-white rounded-xl border border-slate-100">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-slate-500 border-b border-slate-100">
                <th class="px-5 py-3 font-medium">{{ __('Project') }}</th>
                <th class="px-5 py-3 font-medium">{{ __('Project manager') }}</th>
                <th class="px-5 py-3 font-medium">{{ __('Status') }}</th>
                <th class="px-5 py-3 font-medium text-end">{{ __('Total budget') }}</th>
                <th class="px-5 py-3 font-medium text-end">{{ __('Advanced') }}</th>
                <th class="px-5 py-3 font-medium text-end">{{ __('Balance') }}</th>
                <th class="px-5 py-3 font-medium text-end">{{ __('Progress') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($subcontracts as $sc)
                <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50">
                    <td class="px-5 py-3"><a href="{{ route('app.project-manager-budgets.show', $sc) }}" class="font-medium text-brand-700 hover:underline">{{ $sc->project->name }}</a></td>
                    <td class="px-5 py-3">{{ $sc->partyName() }}</td>
                    <td class="px-5 py-3">
                        <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold
                            {{ $sc->status === 'active' ? 'bg-teal-50 text-teal-700' : ($sc->status === 'completed' ? 'bg-slate-100 text-slate-600' : 'bg-red-50 text-red-700') }}">
                            {{ ucfirst($sc->status) }}
                        </span>
                    </td>
                    <td class="px-5 py-3 text-end">{{ number_format((float) $sc->contract_value, 2) }}</td>
                    <td class="px-5 py-3 text-end text-teal-700">{{ number_format($sc->totalPaid(), 2) }}</td>
                    <td class="px-5 py-3 text-end font-semibold">{{ number_format($sc->balanceBeforeRetention(), 2) }}</td>
                    <td class="px-5 py-3 text-end text-slate-500">{{ $sc->percentComplete() }}%</td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-5 py-12 text-center text-slate-400">{{ __('No project manager budgets yet. Create one to start tracking cash given to an employee managing a work package.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
</x-list-toolbar>
@endsection
