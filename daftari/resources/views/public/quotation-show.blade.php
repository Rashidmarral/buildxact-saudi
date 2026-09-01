@extends('layouts.public')

@section('title', $quotation->quotation_number)

@section('content')
@if (session('status'))
    <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 print:hidden">{{ session('status') }}</div>
@endif
@if (session('error'))
    <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 print:hidden">{{ session('error') }}</div>
@endif

<div class="mb-6 flex flex-wrap items-center justify-between gap-3 print:hidden">
    <div>
        <h1 class="text-xl font-bold text-slate-900">{{ $doc['type_label'] }} {{ $quotation->quotation_number }}</h1>
        <p class="text-sm text-slate-500">{{ __('From :company', ['company' => $company->name]) }}</p>
    </div>
    <div class="flex items-center gap-2">
        <a href="{{ route('public.quotations.pdf', ['id' => $quotation->id, 'token' => $quotation->public_token]) }}" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download PDF') }}</a>
        <button onclick="window.print()" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Print') }}</button>
    </div>
</div>

<div class="bg-white rounded-xl border border-slate-100 p-8 print:border-0 print:shadow-none">
    @include('documents.print.body', ['doc' => $doc, 'company' => $company, 'template' => $template])
</div>

<div class="mt-6 print:hidden">
    @if ($quotation->status === 'accepted' || $quotation->status === 'converted')
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-6 py-5 text-center">
            <p class="font-semibold text-emerald-800">{{ __('You accepted this quotation. Thank you!') }}</p>
        </div>
    @elseif ($quotation->status === 'rejected')
        <div class="rounded-xl border border-slate-200 bg-slate-50 px-6 py-5 text-center">
            <p class="font-semibold text-slate-600">{{ __('You declined this quotation.') }}</p>
        </div>
    @elseif ($quotation->isExpired())
        <div class="rounded-xl border border-slate-200 bg-slate-50 px-6 py-5 text-center">
            <p class="font-semibold text-slate-600">{{ __('This quotation has expired.') }}</p>
        </div>
    @else
        <div class="rounded-xl border border-slate-100 bg-white p-6">
            <h2 class="font-semibold text-slate-900 mb-1">{{ __('Your decision') }}</h2>
            <p class="text-sm text-slate-500 mb-4">{{ __('Let :company know whether you would like to proceed.', ['company' => $company->name]) }}</p>

            <div class="flex flex-wrap gap-2">
                <form method="POST" action="{{ route('public.quotations.accept', ['id' => $quotation->id, 'token' => $quotation->public_token]) }}">
                    @csrf
                    <button type="submit" class="rounded-lg bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Accept') }}</button>
                </form>
                <form method="POST" action="{{ route('public.quotations.reject', ['id' => $quotation->id, 'token' => $quotation->public_token]) }}" onsubmit="return confirm('{{ __('Decline this quotation?') }}')">
                    @csrf
                    <button type="submit" class="rounded-lg border border-red-200 px-5 py-2.5 text-sm font-semibold text-red-600 hover:bg-red-50">{{ __('Decline') }}</button>
                </form>
            </div>

            @if ($company->email || $company->phone)
                <p class="mt-4 text-sm text-slate-500">
                    {{ __('Questions about this quotation?') }}
                    @if ($company->email)
                        <a href="mailto:{{ $company->email }}" class="font-semibold text-brand-700 hover:underline">{{ $company->email }}</a>
                    @endif
                    @if ($company->phone)
                        <span class="text-slate-400">·</span> <a href="tel:{{ $company->phone }}" class="font-semibold text-brand-700 hover:underline">{{ $company->phone }}</a>
                    @endif
                </p>
            @endif

            <p class="mt-4 text-sm text-slate-500">
                {{ __('Want to see all your quotes with :company in one place?', ['company' => $company->name]) }}
                <a href="{{ route('portal.login') }}" class="font-semibold text-brand-700 hover:underline">{{ __('Sign in to your account') }}</a>
            </p>
        </div>
    @endif
</div>
@endsection
