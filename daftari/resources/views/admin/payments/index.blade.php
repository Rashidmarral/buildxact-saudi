@extends('layouts.admin')

@section('title', __('Payments'))

@section('content')
<div x-data="{ manual: false }">
    <form method="GET" class="mb-6 bg-white rounded-xl border border-slate-100 p-4 space-y-3">
        <div class="flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Company') }}</label>
                <input type="text" name="q" value="{{ request('q') }}" placeholder="{{ __('Search by company name...') }}" class="rounded-lg border border-slate-200 text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Status') }}</label>
                <select name="status" class="rounded-lg border border-slate-200 text-sm">
                    <option value="">{{ __('All statuses') }}</option>
                    @foreach (\App\Models\Payment::STATUSES as $value)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ ucfirst(str_replace('_', ' ', $value)) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Gateway') }}</label>
                <select name="gateway" class="rounded-lg border border-slate-200 text-sm">
                    <option value="">{{ __('All gateways') }}</option>
                    @foreach ($gateways as $gateway)
                        <option value="{{ $gateway }}" @selected(request('gateway') === $gateway)>{{ ucfirst($gateway) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Plan') }}</label>
                <select name="plan_id" class="rounded-lg border border-slate-200 text-sm">
                    <option value="">{{ __('All plans') }}</option>
                    @foreach ($plans as $plan)
                        <option value="{{ $plan->id }}" @selected((string) request('plan_id') === (string) $plan->id)>{{ $plan->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Amount min') }}</label>
                <input type="number" step="0.01" min="0" name="amount_min" value="{{ request('amount_min') }}" class="w-28 rounded-lg border border-slate-200 text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Amount max') }}</label>
                <input type="number" step="0.01" min="0" name="amount_max" value="{{ request('amount_max') }}" class="w-28 rounded-lg border border-slate-200 text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Date from') }}</label>
                <input type="date" name="date_from" value="{{ request('date_from') }}" class="rounded-lg border border-slate-200 text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Date to') }}</label>
                <input type="date" name="date_to" value="{{ request('date_to') }}" class="rounded-lg border border-slate-200 text-sm">
            </div>
            <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Filter') }}</button>
            @if (request()->anyFilled(['q', 'status', 'gateway', 'plan_id', 'amount_min', 'amount_max', 'date_from', 'date_to']))
                <a href="{{ route('admin.payments.index') }}" class="text-sm font-semibold text-slate-500 hover:underline">{{ __('Clear') }}</a>
            @endif
            <button type="button" @click="manual = true" class="ms-auto rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('+ Record manual payment') }}</button>
        </div>
    </form>

    <div x-show="manual" x-cloak @click.self="manual = false" class="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/40 p-4">
        <div class="w-full max-w-sm rounded-xl bg-white p-6 shadow-lg">
            <h3 class="font-semibold text-slate-900 mb-4">{{ __('Record manual payment') }}</h3>
            <form method="POST" action="{{ route('admin.payments.manual') }}" class="space-y-3">
                @csrf
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Company') }}</label>
                    <select name="company_id" required class="w-full rounded-lg border border-slate-200 text-sm">
                        @foreach (\App\Models\Company::orderBy('name')->get(['id', 'name']) as $c)
                            <option value="{{ $c->id }}">{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Amount') }}</label>
                    <input type="number" step="0.01" min="0.01" name="amount" required class="w-full rounded-lg border border-slate-200 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Reference (optional)') }}</label>
                    <input type="text" name="reference" class="w-full rounded-lg border border-slate-200 text-sm">
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" @click="manual = false" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600">{{ __('Cancel') }}</button>
                    <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Record payment') }}</button>
                </div>
            </form>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-100 overflow-x-auto">
        @if ($payments->isEmpty())
            <p class="px-6 py-8 text-sm text-slate-500">{{ __('No payments match these filters.') }}</p>
        @else
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-slate-500 border-b border-slate-100 whitespace-nowrap">
                        <th class="px-6 py-3 font-medium">{{ __('Date') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Company') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Customer') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Plan') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Amount') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Gateway') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Transaction ID') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Status') }}</th>
                        <th class="px-6 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($payments as $payment)
                        @php $owner = $owners->get($payment->company_id); @endphp
                        <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50">
                            <td class="px-6 py-3">{{ optional($payment->paid_at ?? $payment->created_at)->format('Y-m-d') ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $payment->company_name }}</td>
                            <td class="px-4 py-3">{{ $owner?->name ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $payment->plan->name ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $payment->currency }} {{ number_format($payment->amount, 2) }}</td>
                            <td class="px-4 py-3">{{ $payment->method ? ucfirst($payment->method) : '—' }}</td>
                            <td class="px-4 py-3 font-mono text-xs text-slate-500">{{ $payment->reference ?: '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-semibold {{ $payment->statusBadgeClasses() }}">{{ $payment->statusLabel() }}</span>
                            </td>
                            <td class="px-6 py-3 text-right whitespace-nowrap">
                                <a href="{{ route('admin.payments.show', $payment) }}" class="text-brand-700 hover:underline">{{ __('View') }}</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="mt-4">{{ $payments->links() }}</div>
</div>
@endsection
