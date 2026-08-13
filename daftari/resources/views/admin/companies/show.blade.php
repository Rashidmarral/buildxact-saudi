@extends('layouts.admin')

@section('title', $company->name)

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h2 class="text-lg font-semibold text-slate-900">{{ $company->name }}</h2>
        <p class="text-sm text-slate-500">{{ $company->email }} · {{ $company->vat_number ?: __('No VAT number') }}</p>
    </div>
    @if ($company->status === 'active')
        <form method="POST" action="{{ route('admin.companies.suspend', $company) }}" onsubmit="return confirm('{{ __('Suspend this company?') }}')">
            @csrf
            <button type="submit" class="rounded-lg border border-red-200 text-red-600 px-4 py-2 text-sm font-semibold hover:bg-red-50">{{ __('Suspend') }}</button>
        </form>
    @else
        <form method="POST" action="{{ route('admin.companies.activate', $company) }}">
            @csrf
            <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Activate') }}</button>
        </form>
    @endif
</div>

<div class="grid md:grid-cols-2 gap-6">
    <div class="bg-white rounded-xl border border-slate-100 p-6">
        <h3 class="font-semibold text-slate-900 mb-4">{{ __('Users') }}</h3>
        <ul class="space-y-2 text-sm">
            @foreach ($company->users as $user)
                <li class="flex justify-between">
                    <span>{{ $user->name }} <span class="text-slate-400">({{ $user->role }})</span></span>
                    <span class="text-slate-500">{{ $user->email }}</span>
                </li>
            @endforeach
        </ul>
    </div>
    <div class="bg-white rounded-xl border border-slate-100 p-6">
        <h3 class="font-semibold text-slate-900 mb-4">{{ __('Subscriptions') }}</h3>
        <ul class="space-y-2 text-sm">
            @forelse ($company->subscriptions as $sub)
                <li class="flex justify-between">
                    <span>{{ $sub->plan->name }} — {{ ucfirst($sub->status) }}</span>
                    <span class="text-slate-500">{{ ucfirst($sub->billing_cycle) }}</span>
                </li>
            @empty
                <li class="text-slate-400">{{ __('No subscriptions.') }}</li>
            @endforelse
        </ul>
    </div>
</div>

<div class="mt-6 bg-white rounded-xl border border-slate-100">
    <div class="px-6 py-4 border-b border-slate-100">
        <h3 class="font-semibold text-slate-900">{{ __('Recent payments') }}</h3>
    </div>
    @if ($company->payments->isEmpty())
        <p class="px-6 py-8 text-sm text-slate-500">{{ __('No payments yet.') }}</p>
    @else
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="px-6 py-3 font-medium">{{ __('Date') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Amount') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Status') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($company->payments as $payment)
                    <tr class="border-b border-slate-50 last:border-0">
                        <td class="px-6 py-3">{{ optional($payment->paid_at)->format('Y-m-d') ?? '—' }}</td>
                        <td class="px-6 py-3">SAR {{ number_format($payment->amount, 2) }}</td>
                        <td class="px-6 py-3">{{ ucfirst($payment->status) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
@endsection
