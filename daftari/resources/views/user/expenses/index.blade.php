@extends('layouts.app')

@section('title', __('Expenses'))

@section('content')
<div class="flex items-center justify-between mb-6">
    <p class="text-sm text-slate-500">{{ __('Track vendor purchases and recoverable VAT.') }}</p>
    <a href="{{ route('app.expenses.create') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('+ New expense') }}</a>
</div>

<div class="bg-white rounded-xl border border-slate-100 mb-6 p-5">
    <h2 class="font-semibold text-slate-900 text-sm mb-3">{{ __('Categories') }}</h2>
    <div class="flex flex-wrap gap-2 mb-4">
        @forelse ($categories as $category)
            <span class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-3 py-1 text-xs text-slate-600">
                {{ $category->name }}
                <form method="POST" action="{{ route('app.expense-categories.destroy', $category) }}" onsubmit="return confirm('{{ __('Delete this category?') }}')">
                    @csrf @method('DELETE')
                    <button type="submit" class="text-slate-400 hover:text-red-600">&times;</button>
                </form>
            </span>
        @empty
            <span class="text-xs text-slate-400">{{ __('No categories yet.') }}</span>
        @endforelse
    </div>
    <form method="POST" action="{{ route('app.expense-categories.store') }}" class="flex gap-2">
        @csrf
        <input type="text" name="name" placeholder="{{ __('New category name') }}" required class="rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        <button type="submit" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:border-brand-300">{{ __('Add') }}</button>
    </form>
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
                    <th class="px-6 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($expenses as $expense)
                    <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50">
                        <td class="px-6 py-3">{{ $expense->expense_date->format('Y-m-d') }}</td>
                        <td class="px-6 py-3">{{ $expense->account?->label() ?? $expense->category?->name ?? '—' }}</td>
                        <td class="px-6 py-3">{{ $expense->bankAccount?->name ?? __('Unpaid (payable)') }}</td>
                        <td class="px-6 py-3">SAR {{ number_format($expense->gross_amount ?? ($expense->amount + $expense->vat_amount), 2) }}</td>
                        <td class="px-6 py-3">SAR {{ number_format($expense->vat_amount, 2) }}</td>
                        <td class="px-6 py-3 text-right space-x-3 rtl:space-x-reverse">
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

<div class="mt-4">{{ $expenses->links() }}</div>
@endsection
