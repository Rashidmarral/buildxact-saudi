@extends('layouts.admin')

@section('title', __('ZATCA Submission Detail'))

@section('content')
@php
    $reference = $type === 'invoice' ? ($document->invoice_number ?? '#'.$log->invoice_id) : ($document->credit_note_number ?? '#'.$log->credit_note_id);
    $statusClasses = ['cleared' => 'bg-emerald-50 text-emerald-700', 'reported' => 'bg-emerald-50 text-emerald-700', 'failed' => 'bg-red-50 text-red-700', 'queued' => 'bg-amber-50 text-amber-700'];
    $alreadyAccepted = $type === 'invoice' ? ($document?->isZatcaLocked() ?? false) : ($document?->isZatcaSynced() ?? false);
@endphp

<div class="mb-4">
    <a href="{{ route('admin.zatca.logs') }}" class="text-sm font-semibold text-slate-500 hover:underline">← {{ __('Back to integration logs') }}</a>
</div>

<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <div>
        <h2 class="text-lg font-semibold text-slate-900">{{ $type === 'invoice' ? __('Invoice') : __('Credit note') }} {{ $reference }}</h2>
        <p class="text-sm text-slate-500">{{ $log->company?->name ?? __('Unknown company') }} · {{ ucfirst($log->environment) }} · {{ strtoupper($log->invoice_type) }} ({{ ucfirst($log->direction) }})</p>
    </div>
    <div class="flex items-center gap-3">
        <span class="inline-flex items-center rounded-full px-3 py-1 text-sm font-semibold {{ $statusClasses[$log->status] ?? 'bg-slate-100 text-slate-500' }}">{{ ucfirst($log->status) }}</span>
        @if ($log->xml_payload)
            <a href="{{ route('admin.zatca.logs.xml', ['type' => $type, 'log' => $log->id]) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download XML') }}</a>
        @endif
        @if (! $alreadyAccepted && $document)
            <form method="POST" action="{{ route('admin.zatca.logs.retry', ['type' => $type, 'log' => $log->id]) }}" onsubmit="return confirm('{{ __('Resubmit this document to ZATCA? This creates a new submission attempt — the failed one stays in the log for reference.') }}')">
                @csrf
                <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Retry submission') }}</button>
            </form>
        @endif
    </div>
</div>

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
        <h3 class="font-semibold text-slate-900 mb-4">{{ __('Response') }}</h3>
        @if ($log->response_payload)
            <pre class="max-h-96 overflow-auto rounded-lg bg-slate-50 p-3 text-xs whitespace-pre-wrap break-all">{{ $log->response_payload }}</pre>
        @else
            <p class="text-sm text-slate-500">{{ __('No response recorded yet.') }}</p>
        @endif
    </div>
</div>

<div class="mt-6 bg-white rounded-xl border border-slate-100 p-6">
    <h3 class="font-semibold text-slate-900 mb-4">{{ __('XML') }}</h3>
    @if ($log->xml_payload)
        <pre class="max-h-96 overflow-auto rounded-lg bg-slate-50 p-3 text-xs whitespace-pre-wrap break-all">{{ $log->xml_payload }}</pre>
    @else
        <p class="text-sm text-slate-500">{{ __('No XML was generated for this submission.') }}</p>
    @endif
</div>
@endsection
