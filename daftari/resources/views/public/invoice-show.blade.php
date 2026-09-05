@extends('layouts.public')

@section('title', $invoice->invoice_number)

@section('content')
@if (session('status'))
    <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 print:hidden">{{ session('status') }}</div>
@endif
@if (session('error'))
    <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 print:hidden">{{ session('error') }}</div>
@endif

<div class="mb-6 flex flex-wrap items-center justify-between gap-3 print:hidden">
    <div>
        <h1 class="text-xl font-bold text-slate-900">{{ __('Invoice :number', ['number' => $invoice->invoice_number]) }}</h1>
        <p class="text-sm text-slate-500">{{ __('From :company', ['company' => $company->name]) }}</p>
    </div>
    <div class="flex items-center gap-2">
        <a href="{{ route('public.invoices.pdf', ['id' => $invoice->id, 'token' => $invoice->public_token]) }}" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download PDF') }}</a>
        <button onclick="window.print()" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Print') }}</button>
    </div>
</div>

<div class="bg-white rounded-xl border border-slate-100 p-8 print:border-0 print:shadow-none">
    @include('documents.print.body', ['doc' => $doc, 'company' => $company, 'template' => $template])
</div>

@if ($invoice->installments->isNotEmpty())
    <div class="mt-6 rounded-xl border border-slate-100 bg-white p-6 print:hidden">
        <h2 class="font-semibold text-slate-900 mb-4">{{ __('Payment schedule') }}</h2>
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="py-2">{{ __('Description') }}</th>
                    <th class="py-2">{{ __('Due date') }}</th>
                    <th class="py-2 text-end">{{ __('Amount') }}</th>
                    <th class="py-2 text-end">{{ __('Status') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($invoice->installmentSchedule() as $row)
                    <tr class="border-b border-slate-50 last:border-0">
                        <td class="py-2">{{ $row->installment->description ?: __('Installment') }}</td>
                        <td class="py-2">{{ $row->installment->due_date->format('Y-m-d') }}</td>
                        <td class="py-2 text-end">{{ \App\Support\Money::format($row->installment->amount) }}</td>
                        <td class="py-2 text-end">
                            <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-semibold {{ $row->status === 'paid' ? 'bg-emerald-50 text-emerald-700' : ($row->status === 'partial' ? 'bg-amber-50 text-amber-700' : 'bg-slate-100 text-slate-500') }}">
                                {{ $row->status === 'paid' ? __('Paid') : ($row->status === 'partial' ? __('Partial') : __('Pending')) }}
                            </span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

<div class="mt-6 print:hidden">
    @if ($invoice->balanceDue() <= 0.01)
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-6 py-5 text-center">
            <p class="font-semibold text-emerald-800">{{ __('This invoice is fully paid. Thank you!') }}</p>
        </div>
    @else
        <div class="rounded-xl border border-slate-100 bg-white p-6">
            <h2 class="font-semibold text-slate-900 mb-1">{{ __('How to pay') }}</h2>
            <p class="text-sm text-slate-500 mb-4">{{ __('Balance due: :amount :currency', ['amount' => number_format($invoice->balanceDue(), 2), 'currency' => $invoice->currency]) }}</p>

            @if (! empty($enabledProviders))
                <div class="mb-5 flex flex-wrap gap-2">
                    @foreach ($enabledProviders as $provider)
                        <a href="{{ route('public.invoices.pay', ['id' => $invoice->id, 'token' => $invoice->public_token, 'provider' => $provider]) }}" class="rounded-lg bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-700">
                            {{ __('Pay online with :provider', ['provider' => ucfirst($provider)]) }}
                        </a>
                    @endforeach
                </div>
            @endif

            @if ($showBankTransfer && $doc['bank_account'])
                <div class="rounded-lg border border-slate-100 bg-slate-50 p-4 text-sm">
                    <p class="font-medium text-slate-700 mb-2">{{ __('Pay by bank transfer to:') }}</p>
                    <dl class="grid gap-1.5 sm:grid-cols-2">
                        <div><dt class="inline text-slate-500">{{ __('Bank') }}: </dt><dd class="inline font-medium text-slate-800">{{ $doc['bank_account']->bank_name }}</dd></div>
                        <div><dt class="inline text-slate-500">{{ __('Account name') }}: </dt><dd class="inline font-medium text-slate-800">{{ $doc['bank_account']->account_holder_name }}</dd></div>
                        @if ($doc['bank_account']->iban)
                            <div class="sm:col-span-2"><dt class="inline text-slate-500">{{ __('IBAN') }}: </dt><dd class="inline font-mono font-medium text-slate-800">{{ $doc['bank_account']->iban }}</dd></div>
                        @endif
                        @if ($doc['bank_account']->account_number)
                            <div class="sm:col-span-2"><dt class="inline text-slate-500">{{ __('Account number') }}: </dt><dd class="inline font-mono font-medium text-slate-800">{{ $doc['bank_account']->account_number }}</dd></div>
                        @endif
                    </dl>
                    <p class="mt-3 text-xs text-slate-500">{{ __('Please include the invoice number :number as your payment reference.', ['number' => $invoice->invoice_number]) }}</p>
                </div>
            @else
                <p class="text-sm text-slate-500">{{ __('Please contact :company to arrange payment for this invoice.', ['company' => $company->name]) }}</p>
            @endif

            @if ($company->email || $company->phone)
                <p class="mt-4 text-sm text-slate-500">
                    {{ __('Questions about this invoice?') }}
                    @if ($company->email)
                        <a href="mailto:{{ $company->email }}" class="font-semibold text-brand-700 hover:underline">{{ $company->email }}</a>
                    @endif
                    @if ($company->phone)
                        <span class="text-slate-400">·</span> <a href="tel:{{ $company->phone }}" class="font-semibold text-brand-700 hover:underline">{{ $company->phone }}</a>
                    @endif
                </p>
            @endif

            <p class="mt-4 text-sm text-slate-500">
                {{ __('Want to see all your invoices with :company in one place?', ['company' => $company->name]) }}
                <a href="{{ route('portal.login') }}" class="font-semibold text-brand-700 hover:underline">{{ __('Sign in to your account') }}</a>
            </p>
        </div>
    @endif
</div>
@endsection
