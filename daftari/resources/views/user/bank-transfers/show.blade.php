@extends('layouts.app')

@section('title', __('Transfer'))

@php
    $kind = $transfer->kind();
    $kindLabel = match ($kind) {
        'withdrawal' => __('Withdrawal'),
        'deposit' => __('Deposit'),
        default => __('Transfer'),
    };
@endphp

@section('content')
<div class="flex items-center justify-between mb-6">
    <div class="flex items-center gap-3">
        <h2 class="text-lg font-semibold text-slate-900">{{ $kindLabel }}</h2>
        <span class="inline-block rounded-full bg-slate-100 text-slate-600 text-xs font-medium px-2.5 py-1">{{ \App\Support\PlatformFormat::date($transfer->date) }}</span>
    </div>
    <a href="{{ route('app.bank-transfers.index') }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('← Back') }}</a>
</div>

<div class="max-w-2xl bg-white rounded-xl border border-slate-100 p-6">
    <div class="grid sm:grid-cols-2 gap-6">
        <div>
            <p class="text-xs font-semibold uppercase text-slate-500">{{ __('From account') }}</p>
            <p class="mt-1 font-medium text-slate-900">{{ $transfer->fromAccount->name }}</p>
        </div>
        <div>
            <p class="text-xs font-semibold uppercase text-slate-500">{{ __('To account') }}</p>
            <p class="mt-1 font-medium text-slate-900">{{ $transfer->toAccount->name }}</p>
        </div>
        <div>
            <p class="text-xs font-semibold uppercase text-slate-500">{{ __('Amount') }}</p>
            <p class="mt-1 text-lg font-bold text-slate-900">{{ \App\Support\Money::format($transfer->amount) }}</p>
        </div>
        <div>
            <p class="text-xs font-semibold uppercase text-slate-500">{{ __('Project') }}</p>
            <p class="mt-1 font-medium text-slate-900">{{ $transfer->project?->name ?? '—' }}</p>
        </div>
        @if ($transfer->notes)
            <div class="sm:col-span-2">
                <p class="text-xs font-semibold uppercase text-slate-500">{{ __('Notes') }}</p>
                <p class="mt-1 text-slate-700">{{ $transfer->notes }}</p>
            </div>
        @endif
    </div>
</div>

<div class="max-w-2xl bg-white rounded-xl border border-slate-100 p-6 mt-6">
    <div class="flex items-center justify-between mb-4">
        <h3 class="font-semibold text-slate-900">{{ __('Attachments') }}</h3>
        <button type="button" onclick="document.getElementById('attach-file-input').click()" class="text-sm font-semibold text-brand-700 hover:underline">{{ __('+ Attach file') }}</button>
        <form method="POST" action="{{ route('app.bank-transfers.attachments.store', $transfer) }}" enctype="multipart/form-data" id="attach-file-form" class="hidden">
            @csrf
            <input type="file" name="file" id="attach-file-input" onchange="document.getElementById('attach-file-form').submit()">
        </form>
    </div>
    <p class="text-xs text-slate-400 mb-3">{{ __('Attach the proof for this movement — an ATM slip, a bank transfer confirmation, a photo of the cash count.') }}</p>

    @if ($transfer->attachments->isEmpty())
        <p class="text-sm text-slate-400">{{ __('No attachments') }}</p>
    @else
        <ul class="divide-y divide-slate-50">
            @foreach ($transfer->attachments as $attachment)
                <li class="flex items-center justify-between py-2 text-sm">
                    <a href="{{ Storage::url($attachment->path) }}" target="_blank" class="text-brand-700 hover:underline">{{ $attachment->original_name }}</a>
                    <div class="flex items-center gap-3 text-slate-400">
                        <span>{{ $attachment->humanSize() }}</span>
                        <form method="POST" action="{{ route('app.bank-transfers.attachments.destroy', [$transfer, $attachment]) }}" onsubmit="return confirm('{{ __('Remove this attachment?') }}')">
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
