@extends('layouts.app')

@section('title', __('Expenses'))

@section('content')
<div class="flex items-center justify-between mb-6">
    <p class="text-sm text-slate-500">{{ __('Track vendor purchases and recoverable VAT.') }}</p>
    <a href="{{ route('app.expenses.create') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('+ New expense') }}</a>
</div>

<div class="bg-white rounded-xl border border-slate-100 mb-6 p-5 flex items-center justify-between">
    <div>
        <h2 class="font-semibold text-slate-900 text-sm">{{ __('Categories') }}</h2>
        <p class="text-xs text-slate-400 mt-1">{{ __('Manage categories, codes and which GL account they post to.') }}</p>
    </div>
    <a href="{{ route('app.expense-categories.index') }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-brand-300">{{ __('Manage expense categories') }}</a>
</div>

<div class="bg-white rounded-xl border border-slate-100">
    @if ($expenses->isEmpty())
        <p class="px-6 py-8 text-sm text-slate-500">{{ __('No expenses recorded yet.') }}</p>
    @else
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="px-6 py-3 font-medium">{{ __('Date') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Expense account') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Financial account') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Gross amount') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('VAT') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Status') }}</th>
                    <th class="px-6 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($expenses as $expense)
                    <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50">
                        <td class="px-6 py-3">{{ $expense->expense_date->format('Y-m-d') }}</td>
                        <td class="px-6 py-3">{{ $expense->account?->label() ?? $expense->category?->name ?? '—' }}</td>
                        <td class="px-6 py-3">
                            @if ($expense->paid_by_owner)
                                <span class="inline-flex rounded-full bg-violet-50 px-2 py-0.5 text-xs text-violet-700">{{ __('Owed to owner') }}</span>
                            @elseif ($expense->employee)
                                <span class="inline-flex rounded-full bg-amber-50 px-2 py-0.5 text-xs text-amber-700">{{ __(':employee\'s custody', ['employee' => $expense->employee->full_name]) }}</span>
                            @else
                                {{ $expense->bankAccount?->name ?? __('Unpaid (payable)') }}
                            @endif
                        </td>
                        <td class="px-6 py-3">{{ \App\Support\Money::format($expense->gross_amount ?? ($expense->amount + $expense->vat_amount)) }}</td>
                        <td class="px-6 py-3">{{ \App\Support\Money::format($expense->vat_amount) }}</td>
                        <td class="px-6 py-3">
                            @php
                                $statusColors = ['pending_approval' => 'bg-amber-50 text-amber-700', 'approved' => 'bg-emerald-50 text-emerald-700', 'rejected' => 'bg-red-50 text-red-600'];
                                $statusLabels = ['pending_approval' => __('Pending approval'), 'approved' => __('Approved'), 'rejected' => __('Rejected')];
                            @endphp
                            <span class="inline-block rounded-full px-2.5 py-1 text-xs font-medium {{ $statusColors[$expense->status] ?? 'bg-slate-100 text-slate-600' }}" @if($expense->status === 'rejected' && $expense->rejection_reason) title="{{ $expense->rejection_reason }}" @endif>
                                {{ $statusLabels[$expense->status] ?? $expense->status }}
                            </span>
                        </td>
                        <td class="px-6 py-3 text-right space-x-3 rtl:space-x-reverse">
                            @if ($expense->status === 'pending_approval' && auth()->user()->hasPermission('approvals'))
                                <form method="POST" action="{{ route('app.expenses.approve', $expense) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="text-emerald-600 hover:underline">{{ __('Approve') }}</button>
                                </form>
                                <form method="POST" action="{{ route('app.expenses.reject', $expense) }}" class="inline" onsubmit="return confirm('{{ __('Reject this expense?') }}')">
                                    @csrf
                                    <button type="submit" class="text-red-600 hover:underline">{{ __('Reject') }}</button>
                                </form>
                            @endif
                            <a href="{{ route('app.expenses.edit', $expense) }}" class="text-brand-700 hover:underline">{{ __('Edit') }}</a>
                            <form method="POST" action="{{ route('app.expenses.destroy', $expense) }}" class="inline" onsubmit="return confirm('{{ __('Delete this expense?') }}')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-red-600 hover:underline">{{ __('Delete') }}</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

<div class="mt-4">@include('partials.pagination', ['paginator' => $expenses])</div>
@endsection
