@extends('layouts.app')

@section('title', __('Recurring Expenses'))

@section('content')
<div class="flex items-center justify-between mb-6">
    <p class="text-sm text-slate-500">{{ __('Automatically record expenses like rent or subscriptions on a schedule.') }}</p>
    <a href="{{ route('app.recurring-expenses.create') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('+ New recurring expense') }}</a>
</div>

<div class="bg-white rounded-xl border border-slate-100">
    @if ($recurringExpenses->isEmpty())
        <p class="px-6 py-8 text-sm text-slate-500">{{ __('No recurring expenses yet.') }}</p>
    @else
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="px-6 py-3 font-medium">{{ __('Title') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Category') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Amount') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Frequency') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Next run') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Generated') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Status') }}</th>
                    <th class="px-6 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($recurringExpenses as $recurringExpense)
                    <tr class="border-b border-slate-50 last:border-0">
                        <td class="px-6 py-3 font-medium text-slate-800">{{ $recurringExpense->title }}</td>
                        <td class="px-6 py-3">{{ $recurringExpense->category->name ?? '—' }}</td>
                        <td class="px-6 py-3">{{ \App\Support\Money::format($recurringExpense->gross_amount) }}</td>
                        <td class="px-6 py-3">{{ __(ucfirst($recurringExpense->frequency)) }}</td>
                        <td class="px-6 py-3">{{ $recurringExpense->status === 'active' ? $recurringExpense->next_run_date->format('Y-m-d') : '—' }}</td>
                        <td class="px-6 py-3">{{ $recurringExpense->generated_count }}</td>
                        <td class="px-6 py-3">
                            @php($colors = ['active' => 'bg-emerald-50 text-emerald-700', 'paused' => 'bg-amber-50 text-amber-700', 'completed' => 'bg-slate-100 text-slate-600'])
                            <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $colors[$recurringExpense->status] ?? 'bg-slate-100 text-slate-600' }}">
                                {{ __(ucfirst($recurringExpense->status)) }}
                            </span>
                        </td>
                        <td class="px-6 py-3 text-end">
                            <div class="flex items-center justify-end gap-3">
                                @if ($recurringExpense->status === 'active')
                                    <form method="POST" action="{{ route('app.recurring-expenses.pause', $recurringExpense) }}">
                                        @csrf
                                        <button type="submit" class="text-slate-500 hover:text-slate-700">{{ __('Pause') }}</button>
                                    </form>
                                @elseif ($recurringExpense->status === 'paused')
                                    <form method="POST" action="{{ route('app.recurring-expenses.resume', $recurringExpense) }}">
                                        @csrf
                                        <button type="submit" class="text-brand-700 hover:underline">{{ __('Resume') }}</button>
                                    </form>
                                @endif
                                <a href="{{ route('app.recurring-expenses.edit', $recurringExpense) }}" class="text-brand-700 hover:underline">{{ __('Edit') }}</a>
                                <form method="POST" action="{{ route('app.recurring-expenses.destroy', $recurringExpense) }}" onsubmit="return confirm('{{ __('Delete this recurring expense? This does not affect expenses already generated.') }}')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:underline">{{ __('Delete') }}</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

<div class="mt-4">{{ $recurringExpenses->links() }}</div>
@endsection
