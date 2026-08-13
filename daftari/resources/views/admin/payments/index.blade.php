@extends('layouts.admin')

@section('title', __('Payments'))

@section('content')
<div class="bg-white rounded-xl border border-slate-100">
    @if ($payments->isEmpty())
        <p class="px-6 py-8 text-sm text-slate-500">{{ __('No payments yet.') }}</p>
    @else
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="px-6 py-3 font-medium">{{ __('Date') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Company') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Plan') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Amount') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Method') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Status') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($payments as $payment)
                    <tr class="border-b border-slate-50 last:border-0">
                        <td class="px-6 py-3">{{ optional($payment->paid_at)->format('Y-m-d') ?? '—' }}</td>
                        <td class="px-6 py-3">{{ $payment->company_name }}</td>
                        <td class="px-6 py-3">{{ $payment->plan->name ?? '—' }}</td>
                        <td class="px-6 py-3">SAR {{ number_format($payment->amount, 2) }}</td>
                        <td class="px-6 py-3">{{ $payment->method ?: '—' }}</td>
                        <td class="px-6 py-3">{{ ucfirst($payment->status) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

<div class="mt-4">{{ $payments->links() }}</div>
@endsection
