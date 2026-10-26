@extends('layouts.app')

@section('title', __('Employee Advances'))

@section('content')
<div class="flex items-start justify-between mb-6">
    <div>
        <h2 class="text-lg font-semibold text-slate-900">{{ __('Employee Advances') }}</h2>
        <p class="text-sm text-slate-500 mt-1">{{ __('Every employee with a cash advance on record — what they\'ve been given via Payment Vouchers, minus what they\'ve repaid via Receipt Vouchers and what they\'ve actually spent on real company expenses, as of today.') }}</p>
    </div>
    <a href="{{ route('app.reports.employee-advances', ['export' => 'csv']) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download CSV') }}</a>
</div>

<div class="grid sm:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-slate-100 p-5">
        <p class="text-xs font-semibold uppercase text-slate-500">{{ __('Total advanced') }}</p>
        <p class="text-xl font-semibold text-slate-900 mt-1">{{ \App\Support\Money::format($totalAdvanced) }}</p>
    </div>
    <div class="bg-white rounded-xl border border-slate-100 p-5">
        <p class="text-xs font-semibold uppercase text-slate-500">{{ __('Total settled') }}</p>
        <p class="text-xl font-semibold text-slate-900 mt-1">{{ \App\Support\Money::format($totalSettled) }}</p>
        <p class="text-[11px] text-slate-400 mt-0.5">{{ __('Unused cash physically returned') }}</p>
    </div>
    <div class="bg-white rounded-xl border border-slate-100 p-5">
        <p class="text-xs font-semibold uppercase text-slate-500">{{ __('Total spent') }}</p>
        <p class="text-xl font-semibold text-slate-900 mt-1">{{ \App\Support\Money::format($totalSpent) }}</p>
        <p class="text-[11px] text-slate-400 mt-0.5">{{ __('Real expenses recorded from this cash') }}</p>
    </div>
    <div class="bg-white rounded-xl border border-slate-100 p-5">
        <p class="text-xs font-semibold uppercase text-slate-500">{{ __('Outstanding') }}</p>
        <p class="text-xl font-semibold {{ $totalOutstanding > 0 ? 'text-amber-600' : 'text-slate-900' }} mt-1">{{ \App\Support\Money::format($totalOutstanding) }}</p>
    </div>
</div>

<div class="bg-white rounded-xl border border-slate-100 overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-slate-500 border-b border-slate-100">
                <th class="px-5 py-3 font-medium">{{ __('Employee') }}</th>
                <th class="px-5 py-3 font-medium">{{ __('Employee No.') }}</th>
                <th class="px-5 py-3 font-medium text-end">{{ __('Advanced') }}</th>
                <th class="px-5 py-3 font-medium text-end">{{ __('Settled') }}</th>
                <th class="px-5 py-3 font-medium text-end">{{ __('Spent') }}</th>
                <th class="px-5 py-3 font-medium text-end">{{ __('Outstanding') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr class="border-b border-slate-50 last:border-0">
                    <td class="px-5 py-3 font-medium text-slate-800">{{ $row['employee']->full_name }}</td>
                    <td class="px-5 py-3 text-slate-500">{{ $row['employee']->employee_number }}</td>
                    <td class="px-5 py-3 text-end">{{ \App\Support\Money::format($row['advanced']) }}</td>
                    <td class="px-5 py-3 text-end">{{ \App\Support\Money::format($row['settled']) }}</td>
                    <td class="px-5 py-3 text-end">{{ \App\Support\Money::format($row['spent']) }}</td>
                    <td class="px-5 py-3 text-end font-semibold {{ $row['outstanding'] > 0 ? 'text-amber-600' : 'text-slate-900' }}">{{ \App\Support\Money::format($row['outstanding']) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-5 py-10 text-center text-slate-400">{{ __('No employee advances on record.') }}</td></tr>
            @endforelse
        </tbody>
        @if ($rows->isNotEmpty())
            <tfoot>
                <tr class="border-t-2 border-slate-800 font-semibold text-slate-900">
                    <td class="px-5 py-3" colspan="2">{{ __('Total') }}</td>
                    <td class="px-5 py-3 text-end">{{ \App\Support\Money::format($totalAdvanced) }}</td>
                    <td class="px-5 py-3 text-end">{{ \App\Support\Money::format($totalSettled) }}</td>
                    <td class="px-5 py-3 text-end">{{ \App\Support\Money::format($totalSpent) }}</td>
                    <td class="px-5 py-3 text-end">{{ \App\Support\Money::format($totalOutstanding) }}</td>
                </tr>
            </tfoot>
        @endif
    </table>
</div>
@endsection
