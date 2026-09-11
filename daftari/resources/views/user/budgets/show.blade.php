@extends('layouts.app')

@section('title', $budget->name)

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-xl font-bold text-slate-900">{{ $budget->name }}</h1>
        <p class="text-sm text-slate-500 mt-1">{{ __('Fiscal year') }} {{ $budget->fiscal_year }}</p>
    </div>
    <div class="flex items-center gap-2">
        @if ($budget->status === 'draft')
            <form method="POST" action="{{ route('app.budgets.activate', $budget) }}">
                @csrf
                <button type="submit" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Activate') }}</button>
            </form>
        @else
            <span class="rounded-full bg-green-100 px-3 py-1 text-xs font-medium text-green-700">{{ __('Active') }}</span>
        @endif
        <a href="{{ route('app.budgets.edit', $budget) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Edit') }}</a>
        <form method="POST" action="{{ route('app.budgets.destroy', $budget) }}" onsubmit="return confirm('{{ __('Delete this budget?') }}')">
            @csrf
            @method('DELETE')
            <button type="submit" class="rounded-lg border border-red-200 px-4 py-2 text-sm font-semibold text-red-600 hover:bg-red-50">{{ __('Delete') }}</button>
        </form>
    </div>
</div>

<div class="grid sm:grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-slate-100 p-5">
        <p class="text-xs font-medium text-slate-500">{{ __('Total budgeted') }}</p>
        <p class="text-xl font-bold text-slate-900 mt-1">{{ \App\Support\Money::format($totalBudgeted) }}</p>
    </div>
    <div class="bg-white rounded-xl border border-slate-100 p-5">
        <p class="text-xs font-medium text-slate-500">{{ __('Total actual') }}</p>
        <p class="text-xl font-bold text-slate-900 mt-1">{{ \App\Support\Money::format($totalActual) }}</p>
    </div>
    <div class="bg-white rounded-xl border border-slate-100 p-5">
        <p class="text-xs font-medium text-slate-500">{{ __('Variance') }}</p>
        @php($totalVariance = $totalActual - $totalBudgeted)
        <p class="text-xl font-bold mt-1 {{ $totalVariance > 0 ? 'text-red-600' : 'text-emerald-600' }}">{{ \App\Support\Money::format($totalVariance) }}</p>
    </div>
</div>

<div class="bg-white rounded-xl border border-slate-100">
    @if ($rows->isEmpty())
        <p class="px-6 py-8 text-sm text-slate-500">{{ __('No account budgets set yet.') }} <a href="{{ route('app.budgets.edit', $budget) }}" class="text-brand-700 font-semibold hover:underline">{{ __('Add some') }}</a>.</p>
    @else
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="px-6 py-3 font-medium">{{ __('Account') }}</th>
                    <th class="px-6 py-3 font-medium text-end">{{ __('Budgeted') }}</th>
                    <th class="px-6 py-3 font-medium text-end">{{ __('Actual') }}</th>
                    <th class="px-6 py-3 font-medium text-end">{{ __('Variance') }}</th>
                    <th class="px-6 py-3 font-medium text-end">{{ __('% used') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr class="border-b border-slate-50 last:border-0">
                        <td class="px-6 py-3">
                            <span class="text-slate-400">{{ $row['account']->code }}</span> {{ $row['account']->name }}
                        </td>
                        <td class="px-6 py-3 text-end">{{ \App\Support\Money::format($row['budgeted']) }}</td>
                        <td class="px-6 py-3 text-end">{{ \App\Support\Money::format($row['actual']) }}</td>
                        <td class="px-6 py-3 text-end font-medium {{ $row['unfavorable'] ? 'text-red-600' : 'text-emerald-600' }}">{{ \App\Support\Money::format($row['variance']) }}</td>
                        <td class="px-6 py-3 text-end text-slate-500">
                            {{ $row['budgeted'] > 0 ? number_format(($row['actual'] / $row['budgeted']) * 100, 0) : '—' }}{{ $row['budgeted'] > 0 ? '%' : '' }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

<a href="{{ route('app.budgets.index') }}" class="mt-6 inline-block text-sm font-semibold text-brand-700 hover:underline">{{ __('Back to budgets') }}</a>
@endsection
