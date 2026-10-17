@extends('layouts.app')

@section('title', $voucher->voucher_number)

@section('content')
<style>
    @media print {
        @page { size: A4; margin: 12mm; }
    }
</style>
<div class="flex items-center justify-between mb-6 print:hidden">
    <div class="flex items-center gap-3">
        <h2 class="text-lg font-semibold text-slate-900">{{ $voucher->voucher_number }}</h2>
        @if ($voucher->status === 'void')
            <span class="inline-block rounded-full bg-red-50 text-red-600 text-xs font-medium px-2.5 py-1">{{ __('Void') }}</span>
        @else
            <span class="inline-block rounded-full bg-brand-50 text-brand-700 text-xs font-medium px-2.5 py-1">{{ __('Issued') }}</span>
        @endif
    </div>
    <div class="flex items-center gap-3">
        @if ($voucher->status === 'issued')
            <a href="{{ route('app.payment-vouchers.edit', $voucher) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Edit') }}</a>
            <form method="POST" action="{{ route('app.payment-vouchers.void', $voucher) }}" onsubmit="return confirm('{{ __('Void this payment voucher?') }}')">
                @csrf
                <button type="submit" class="rounded-lg border border-red-200 text-red-600 px-4 py-2 text-sm font-semibold hover:bg-red-50">{{ __('Void') }}</button>
            </form>
        @else
            <form method="POST" action="{{ route('app.payment-vouchers.destroy', $voucher) }}" onsubmit="return confirm('{{ __('Permanently delete this voided voucher? This cannot be undone.') }}')">
                @csrf
                @method('DELETE')
                <button type="submit" class="rounded-lg border border-red-200 text-red-600 px-4 py-2 text-sm font-semibold hover:bg-red-50">{{ __('Delete') }}</button>
            </form>
        @endif
        <a href="{{ route('app.payment-vouchers.pdf', $voucher) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download PDF') }}</a>
        <button onclick="window.print()" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Print / PDF') }}</button>
    </div>
</div>

<div class="max-w-3xl bg-white rounded-xl border border-slate-100 p-8 print:border-0 print:shadow-none print:max-w-none">
    @include('documents.print.chrome-header', ['company' => $voucher->company, 'template' => $template])
    @include('documents.print.voucher-body', ['voucher' => $voucher, 'type' => 'payment'])
    @include('documents.print.chrome-footer', ['company' => $voucher->company, 'template' => $template])
</div>

<div class="max-w-3xl bg-white rounded-xl border border-slate-100 p-6 mt-6 print:hidden">
    <div class="flex items-center justify-between mb-4">
        <h3 class="font-semibold text-slate-900">{{ __('Attachments') }}</h3>
        <button type="button" onclick="document.getElementById('attach-file-input').click()" class="text-sm font-semibold text-brand-700 hover:underline">{{ __('+ Attach file') }}</button>
        <form method="POST" action="{{ route('app.payment-vouchers.attachments.store', $voucher) }}" enctype="multipart/form-data" id="attach-file-form" class="hidden">
            @csrf
            <input type="file" name="file" id="attach-file-input" onchange="document.getElementById('attach-file-form').submit()">
        </form>
    </div>
    <p class="text-xs text-slate-400 mb-3">{{ __('Attach the proof for this payment — a receipt, an invoice from the payee, a signed acknowledgement.') }}</p>

    @if ($voucher->attachments->isEmpty())
        <p class="text-sm text-slate-400">{{ __('No attachments') }}</p>
    @else
        <ul class="divide-y divide-slate-50">
            @foreach ($voucher->attachments as $attachment)
                <li class="flex items-center justify-between py-2 text-sm">
                    <a href="{{ Storage::url($attachment->path) }}" target="_blank" class="text-brand-700 hover:underline">{{ $attachment->original_name }}</a>
                    <div class="flex items-center gap-3 text-slate-400">
                        <span>{{ $attachment->humanSize() }}</span>
                        <form method="POST" action="{{ route('app.payment-vouchers.attachments.destroy', [$voucher, $attachment]) }}" onsubmit="return confirm('{{ __('Remove this attachment?') }}')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-red-600 hover:underline">{{ __('Remove') }}</button>
                        </form>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</div>
@endsection
