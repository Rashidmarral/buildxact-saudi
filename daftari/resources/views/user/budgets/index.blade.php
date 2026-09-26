@extends('layouts.app')

@section('title', __('Budgets'))

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-xl font-bold text-slate-900">{{ __('Budgets') }}</h1>
        <p class="text-sm text-slate-500 mt-1">{{ __('Set annual budgets per account and track actual spend against them.') }}</p>
    </div>
    <a href="{{ route('app.budgets.create') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('New budget') }}</a>
</div>

<div class="bg-white rounded-xl border border-slate-100">
    @if ($budgets->isEmpty())
        <p class="px-6 py-8 text-sm text-slate-500">{{ __('No budgets yet.') }}</p>
    @else
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="px-6 py-3 font-medium">{{ __('Name') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Fiscal year') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Accounts') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Status') }}</th>
                    <th class="px-6 py-3 font-medium"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($budgets as $budget)
                    <tr class="border-b border-slate-50 last:border-0">
                        <td class="px-6 py-3 font-medium text-slate-900">{{ $budget->name }}</td>
                        <td class="px-6 py-3">{{ $budget->fiscal_year }}</td>
                        <td class="px-6 py-3 text-slate-500">{{ $budget->lines_count }}</td>
                        <td class="px-6 py-3">
                            @if ($budget->status === 'active')
                                <span class="rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-700">{{ __('Active') }}</span>
                            @else
                                <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600">{{ __('Draft') }}</span>
                            @endif
                        </td>
                        <td class="px-6 py-3 text-right space-x-3 rtl:space-x-reverse">
                            <a href="{{ route('app.budgets.show', $budget) }}" class="text-xs font-semibold text-brand-700 hover:underline">{{ __('View') }}</a>
                            <a href="{{ route('app.budgets.edit', $budget) }}" class="text-xs font-semibold text-brand-700 hover:underline">{{ __('Edit') }}</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

<div class="mt-4">{{ $budgets->links() }}</div>
@endsection
