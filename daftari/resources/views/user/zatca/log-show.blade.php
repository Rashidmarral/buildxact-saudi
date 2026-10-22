@extends('layouts.app')

@section('title', __('ZATCA Submission Detail'))

@section('content')
@php
    $reference = match ($type) {
        'invoice' => $document->invoice_number ?? ($document->prepayment_number ?? '#'.$log->id),
        'credit_note' => $document->credit_note_number ?? '#'.$log->id,
        'debit_note' => $document->debit_note_number ?? '#'.$log->id,
    };
    $typeLabel = match ($type) {
        'invoice' => __('Invoice'),
        'credit_note' => __('Credit note'),
        'debit_note' => __('Debit note'),
    };
    $statusClasses = ['cleared' => 'bg-emerald-50 text-emerald-700', 'reported' => 'bg-emerald-50 text-emerald-700', 'failed' => 'bg-red-50 text-red-700', 'queued' => 'bg-amber-50 text-amber-700'];
@endphp

<div class="mb-4">
    <a href="{{ route('app.zatca.dashboard') }}" class="text-sm font-semibold text-slate-500 hover:underline">← {{ __('Back to ZATCA integration') }}</a>
</div>

<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <div>
        <h2 class="text-lg font-semibold text-slate-900">{{ $typeLabel }} {{ $reference }}</h2>
        <p class="text-sm text-slate-500">{{ ucfirst($log->environment) }} · {{ strtoupper($log->invoice_type) }} ({{ ucfirst($log->direction) }})</p>
    </div>
    <span class="inline-flex items-center rounded-full px-3 py-1 text-sm font-semibold {{ $statusClasses[$log->status] ?? 'bg-slate-100 text-slate-500' }}">{{ ucfirst($log->status) }}</span>
</div>

@if (!empty($messages['errors']))
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-4">
        <h3 class="font-semibold text-red-800 mb-2">{{ __('Errors from ZATCA') }}</h3>
        <ul class="list-disc list-inside space-y-1 text-sm text-red-700">
            @foreach ($messages['errors'] as $message)
                <li>{{ is_array($message) ? ($message['message'] ?? json_encode($message)) : $message }}</li>
            @endforeach
        </ul>
    </div>
@endif

@if (!empty($messages['warnings']))
    <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-4">
        <h3 class="font-semibold text-amber-800 mb-2">{{ __('Warnings from ZATCA') }}</h3>
        <p class="text-xs text-amber-700 mb-2">{{ __('These did not block clearance/reporting, but are worth reviewing — ZATCA flagged something about this submission.') }}</p>
        <ul class="list-disc list-inside space-y-1 text-sm text-amber-700">
            @foreach ($messages['warnings'] as $message)
                <li>{{ is_array($message) ? ($message['message'] ?? json_encode($message)) : $message }}</li>
            @endforeach
        </ul>
    </div>
@endif

@if (!empty($messages['infos']))
    <div class="mb-4 rounded-lg border border-slate-200 bg-slate-50 p-4">
        <h3 class="font-semibold text-slate-700 mb-2">{{ __('Additional information from ZATCA') }}</h3>
        <ul class="list-disc list-inside space-y-1 text-sm text-slate-600">
            @foreach ($messages['infos'] as $message)
                <li>{{ is_array($message) ? ($message['message'] ?? json_encode($message)) : $message }}</li>
            @endforeach
        </ul>
    </div>
@endif

@if (empty($messages['errors']) && empty($messages['warnings']) && empty($messages['infos']) && in_array($log->status, ['cleared', 'reported'], true))
    <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-700">
        {{ __('ZATCA returned no warnings or additional messages for this submission — a clean clearance/reporting result.') }}
    </div>
@endif

<div class="grid lg:grid-cols-2 gap-6">
    <div class="bg-white rounded-xl border border-slate-100 p-6">
        <h3 class="font-semibold text-slate-900 mb-4">{{ __('Submission details') }}</h3>
        <dl class="space-y-2 text-sm">
            <div class="flex justify-between gap-4"><dt class="text-slate-500">{{ __('UUID') }}</dt><dd class="font-mono text-xs text-right break-all">{{ $log->request_uuid }}</dd></div>
            <div class="flex justify-between gap-4"><dt class="text-slate-500">{{ __('Invoice hash') }}</dt><dd class="font-mono text-xs text-right break-all">{{ \Illuminate\Support\Str::limit($log->invoice_hash, 40) ?: '—' }}</dd></div>
            <div class="flex justify-between gap-4"><dt class="text-slate-500">{{ __('Previous invoice hash') }}</dt><dd class="font-mono text-xs text-right break-all">{{ \Illuminate\Support\Str::limit($log->previous_invoice_hash, 40) ?: '—' }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">{{ __('Submission date') }}</dt><dd class="font-medium">{{ $log->submitted_at?->format('Y-m-d H:i') ?? '—' }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">{{ __('Cleared/reported at') }}</dt><dd class="font-medium">{{ $log->cleared_at?->format('Y-m-d H:i') ?? '—' }}</dd></div>
        </dl>
        @if ($log->error_message)
            <div class="mt-4 rounded-lg border border-red-100 bg-red-50 p-3 text-sm text-red-700">
                <div class="font-semibold mb-1">{{ __('Error message') }}</div>
                {{ $log->error_message }}
            </div>
        @endif
    </div>

    <div class="bg-white rounded-xl border border-slate-100 p-6">
        <h3 class="font-semibold text-slate-900 mb-4">{{ __('Raw response') }}</h3>
        @if ($log->response_payload)
            <pre class="max-h-96 overflow-auto rounded-lg bg-slate-50 p-3 text-xs whitespace-pre-wrap break-all">{{ $log->response_payload }}</pre>
        @else
            <p class="text-sm text-slate-500">{{ __('No response recorded yet.') }}</p>
        @endif
    </div>
</div>

<div class="mt-6 bg-white rounded-xl border border-slate-100 p-6">
    <h3 class="font-semibold text-slate-900 mb-4">{{ __('XML submitted') }}</h3>
    @if ($log->xml_payload)
        <pre class="max-h-96 overflow-auto rounded-lg bg-slate-50 p-3 text-xs whitespace-pre-wrap break-all">{{ $log->xml_payload }}</pre>
    @else
        <p class="text-sm text-slate-500">{{ __('No XML was generated for this submission.') }}</p>
    @endif
</div>
@endsection
