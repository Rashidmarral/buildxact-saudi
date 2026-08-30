@extends('layouts.admin')

@section('title', __('ZATCA Integration Logs'))

@section('content')
<div class="mb-4">
    <a href="{{ route('admin.zatca.index') }}" class="text-sm font-semibold text-slate-500 hover:underline">← {{ __('Back to ZATCA dashboard') }}</a>
</div>

<form method="GET" class="mb-6 bg-white rounded-xl border border-slate-100 p-4 space-y-3">
    <div class="flex flex-wrap items-end gap-3">
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Company') }}</label>
            <select name="company_id" class="rounded-lg border border-slate-200 text-sm">
                <option value="">{{ __('All companies') }}</option>
                @foreach ($companies as $company)
                    <option value="{{ $company->id }}" @selected((string) request('company_id') === (string) $company->id)>{{ $company->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Status') }}</label>
            <select name="status" class="rounded-lg border border-slate-200 text-sm">
                <option value="">{{ __('All statuses') }}</option>
                @foreach (['queued' => __('Queued'), 'cleared' => __('Cleared'), 'reported' => __('Reported'), 'failed' => __('Failed')] as $value => $label)
                    <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Environment') }}</label>
            <select name="environment" class="rounded-lg border border-slate-200 text-sm">
                <option value="">{{ __('All environments') }}</option>
                @foreach (array_keys(\App\Services\Zatca\ZatcaApiClient::BASE_URLS) as $env)
                    <option value="{{ $env }}" @selected(request('environment') === $env)>{{ ucfirst($env) }}</option>
                @endforeach
            </select>
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
        @if (request()->anyFilled(['company_id', 'status', 'environment', 'date_from', 'date_to']))
            <a href="{{ route('admin.zatca.logs') }}" class="text-sm font-semibold text-slate-500 hover:underline">{{ __('Clear') }}</a>
        @endif
    </div>
</form>

@php
    $statusClasses = ['cleared' => 'bg-emerald-50 text-emerald-700', 'reported' => 'bg-emerald-50 text-emerald-700', 'failed' => 'bg-red-50 text-red-700', 'queued' => 'bg-amber-50 text-amber-700'];
@endphp

<h2 class="text-base font-semibold text-slate-900 mb-3">{{ __('Invoices') }}</h2>
<div class="bg-white rounded-xl border border-slate-100 overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-slate-500 border-b border-slate-100 whitespace-nowrap">
                <th class="px-6 py-3 font-medium">{{ __('Company') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Invoice') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('UUID') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Invoice type') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Submission date') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Status') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Error message') }}</th>
                <th class="px-6 py-3"></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($invoiceLogs as $log)
                <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50 align-top">
                    <td class="px-6 py-3">{{ $log->company?->name ?? '—' }}</td>
                    <td class="px-4 py-3">{{ $log->invoice?->invoice_number ?? '#'.$log->invoice_id }}</td>
                    <td class="px-4 py-3 font-mono text-xs text-slate-500">{{ \Illuminate\Support\Str::limit($log->request_uuid, 13) }}</td>
                    <td class="px-4 py-3">{{ strtoupper($log->invoice_type) }} · {{ ucfirst($log->direction) }}</td>
                    <td class="px-4 py-3">{{ $log->submitted_at?->format('Y-m-d H:i') ?? '—' }}</td>
                    <td class="px-4 py-3"><span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold {{ $statusClasses[$log->status] ?? 'bg-slate-100 text-slate-500' }}">{{ ucfirst($log->status) }}</span></td>
                    <td class="px-4 py-3 max-w-xs">
                        @if ($log->error_message)
                            <span class="text-red-600">{{ \Illuminate\Support\Str::limit($log->error_message, 60) }}</span>
                        @else
                            <span class="text-slate-400">—</span>
                        @endif
                    </td>
                    <td class="px-6 py-3 text-right whitespace-nowrap">
                        <a href="{{ route('admin.zatca.logs.show', ['type' => 'invoice', 'log' => $log->id]) }}" class="text-brand-700 hover:underline">{{ __('View') }}</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="px-6 py-8 text-center text-slate-400">{{ __('No invoice submissions match these filters.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-3">{{ $invoiceLogs->links() }}</div>

<h2 class="text-base font-semibold text-slate-900 mb-3 mt-8">{{ __('Credit notes') }}</h2>
<div class="bg-white rounded-xl border border-slate-100 overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-slate-500 border-b border-slate-100 whitespace-nowrap">
                <th class="px-6 py-3 font-medium">{{ __('Company') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Credit note') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('UUID') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Invoice type') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Submission date') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Status') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Error message') }}</th>
                <th class="px-6 py-3"></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($creditNoteLogs as $log)
                <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50 align-top">
                    <td class="px-6 py-3">{{ $log->company?->name ?? '—' }}</td>
                    <td class="px-4 py-3">{{ $log->creditNote?->credit_note_number ?? '#'.$log->credit_note_id }}</td>
                    <td class="px-4 py-3 font-mono text-xs text-slate-500">{{ \Illuminate\Support\Str::limit($log->request_uuid, 13) }}</td>
                    <td class="px-4 py-3">{{ strtoupper($log->invoice_type) }} · {{ ucfirst($log->direction) }}</td>
                    <td class="px-4 py-3">{{ $log->submitted_at?->format('Y-m-d H:i') ?? '—' }}</td>
                    <td class="px-4 py-3"><span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold {{ $statusClasses[$log->status] ?? 'bg-slate-100 text-slate-500' }}">{{ ucfirst($log->status) }}</span></td>
                    <td class="px-4 py-3 max-w-xs">
                        @if ($log->error_message)
                            <span class="text-red-600">{{ \Illuminate\Support\Str::limit($log->error_message, 60) }}</span>
                        @else
                            <span class="text-slate-400">—</span>
                        @endif
                    </td>
                    <td class="px-6 py-3 text-right whitespace-nowrap">
                        <a href="{{ route('admin.zatca.logs.show', ['type' => 'credit_note', 'log' => $log->id]) }}" class="text-brand-700 hover:underline">{{ __('View') }}</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="px-6 py-8 text-center text-slate-400">{{ __('No credit note submissions match these filters.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-3">{{ $creditNoteLogs->links() }}</div>
@endsection
