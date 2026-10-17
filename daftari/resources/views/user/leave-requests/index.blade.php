@extends('layouts.app')

@section('title', __('Leave'))

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">{{ __('Leave') }}</h1>
        <p class="text-sm text-slate-500 mt-1">{{ __('Annual leave balances accrue automatically under Saudi Labor Law (21 days/year, 30 after 5 years) — request and approve time off against them here.') }}</p>
    </div>
    <button type="button" onclick="document.getElementById('new-leave-modal').showModal()" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('+ Request leave') }}</button>
</div>

@if (session('status'))
    <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
@endif
@if ($errors->any())
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
@endif

<h2 class="text-sm font-semibold uppercase text-slate-500 mb-3">{{ __('Annual leave balances') }}</h2>
<div class="bg-white rounded-xl border border-slate-100 overflow-hidden mb-8">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-slate-500 border-b border-slate-100">
                <th class="px-5 py-3 font-medium">{{ __('Employee') }}</th>
                <th class="px-5 py-3 font-medium text-end">{{ __('Accrued') }}</th>
                <th class="px-5 py-3 font-medium text-end">{{ __('Taken') }}</th>
                <th class="px-5 py-3 font-medium text-end">{{ __('Balance') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($employees as $employee)
                @php($balance = $balances[$employee->id])
                <tr class="border-b border-slate-50 last:border-0">
                    <td class="px-5 py-3 font-medium text-slate-800">{{ $employee->full_name }}</td>
                    <td class="px-5 py-3 text-end tabular-nums">{{ number_format($balance['accrued'], 2) }}</td>
                    <td class="px-5 py-3 text-end tabular-nums">{{ number_format($balance['taken'], 2) }}</td>
                    <td class="px-5 py-3 text-end tabular-nums font-semibold">{{ number_format($balance['balance'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="px-5 py-8 text-center text-slate-400">{{ __('No active employees yet.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<h2 class="text-sm font-semibold uppercase text-slate-500 mb-3">{{ __('Leave requests') }}</h2>
<div class="bg-white rounded-xl border border-slate-100 overflow-hidden">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-slate-500 border-b border-slate-100">
                <th class="px-5 py-3 font-medium">{{ __('Employee') }}</th>
                <th class="px-5 py-3 font-medium">{{ __('Type') }}</th>
                <th class="px-5 py-3 font-medium">{{ __('Dates') }}</th>
                <th class="px-5 py-3 font-medium text-end">{{ __('Days') }}</th>
                <th class="px-5 py-3 font-medium">{{ __('Status') }}</th>
                <th class="px-5 py-3 font-medium"></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($requests as $leaveRequest)
                <tr class="border-b border-slate-50 last:border-0">
                    <td class="px-5 py-3 font-medium text-slate-800">{{ $leaveRequest->employee->full_name }}</td>
                    <td class="px-5 py-3 capitalize">{{ __(ucfirst($leaveRequest->type)) }}</td>
                    <td class="px-5 py-3 text-slate-500">{{ $leaveRequest->start_date->format('Y-m-d') }} &rarr; {{ $leaveRequest->end_date->format('Y-m-d') }}</td>
                    <td class="px-5 py-3 text-end tabular-nums">{{ number_format($leaveRequest->days, 2) }}</td>
                    <td class="px-5 py-3">
                        @if ($leaveRequest->status === 'approved')
                            <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700">{{ __('Approved') }}</span>
                        @elseif ($leaveRequest->status === 'rejected')
                            <span class="rounded-full bg-red-50 px-2.5 py-0.5 text-xs font-semibold text-red-700">{{ __('Rejected') }}</span>
                        @else
                            <span class="rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-700">{{ __('Pending') }}</span>
                        @endif
                    </td>
                    <td class="px-5 py-3 text-end">
                        @if ($leaveRequest->isPending())
                            <div class="flex justify-end gap-2">
                                <form method="POST" action="{{ route('app.leave-requests.approve', $leaveRequest) }}">
                                    @csrf
                                    <button type="submit" class="text-xs font-semibold text-emerald-700 hover:underline">{{ __('Approve') }}</button>
                                </form>
                                <form method="POST" action="{{ route('app.leave-requests.reject', $leaveRequest) }}">
                                    @csrf
                                    <button type="submit" class="text-xs font-semibold text-red-600 hover:underline">{{ __('Reject') }}</button>
                                </form>
                            </div>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-5 py-8 text-center text-slate-400">{{ __('No leave requests yet.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<dialog id="new-leave-modal" class="rounded-2xl border border-slate-100 p-0 w-full max-w-md backdrop:bg-slate-900/40">
    <form method="POST" action="{{ route('app.leave-requests.store') }}" class="p-6 space-y-4">
        @csrf
        <div class="flex items-start justify-between">
            <h3 class="text-lg font-bold text-slate-900">{{ __('Request leave') }}</h3>
            <button type="button" onclick="document.getElementById('new-leave-modal').close()" class="text-slate-400 hover:text-slate-600">✕</button>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500">{{ __('Employee') }}</label>
            <select name="employee_id" required class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                @foreach ($employees as $employee)
                    <option value="{{ $employee->id }}">{{ $employee->full_name }} — {{ __('Balance') }}: {{ number_format($balances[$employee->id]['balance'], 2) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500">{{ __('Type') }}</label>
            <select name="type" required class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                <option value="annual">{{ __('Annual') }}</option>
                <option value="sick">{{ __('Sick') }}</option>
                <option value="unpaid">{{ __('Unpaid') }}</option>
            </select>
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-xs font-medium text-slate-500">{{ __('Start date') }}</label>
                <input type="date" name="start_date" required class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500">{{ __('End date') }}</label>
                <input type="date" name="end_date" required class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
            </div>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500">{{ __('Reason (optional)') }}</label>
            <input type="text" name="reason" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div class="flex gap-3">
            <button type="button" onclick="document.getElementById('new-leave-modal').close()" class="flex-1 rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Cancel') }}</button>
            <button type="submit" class="flex-1 rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Submit') }}</button>
        </div>
    </form>
</dialog>
@endsection
