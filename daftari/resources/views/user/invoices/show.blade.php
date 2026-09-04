@extends('layouts.app')

@section('title', $invoice->invoice_number)

@section('content')
<div class="flex items-center justify-between mb-6 print:hidden">
    <div class="flex items-center gap-3">
        <h2 class="text-lg font-semibold text-slate-900">{{ $invoice->invoice_number }}</h2>
        @include('user.invoices.partials.status-badge', ['status' => $invoice->status])
        @if ($invoice->isZatcaLocked())
            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                @include('partials.icon', ['name' => 'shield', 'class' => 'h-3.5 w-3.5'])
                {{ __('ZATCA cleared — locked') }}
            </span>
        @elseif ($invoice->currency !== $invoice->company->currency)
            <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700" title="{{ __('ZATCA e-invoicing sync only supports invoices in your base currency (:currency).', ['currency' => $invoice->company->currency]) }}">
                {{ __('ZATCA sync skipped — foreign currency') }}
            </span>
        @endif
    </div>
    <div class="flex items-center gap-3">
        @if ($invoice->status === 'draft')
            <form method="POST" action="{{ route('app.invoices.send', $invoice) }}">
                @csrf
                <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Mark as sent') }}</button>
            </form>
        @endif
        @if ($invoice->status === 'pending_approval' && auth()->user()->hasPermission('approvals'))
            <form method="POST" action="{{ route('app.invoices.approve', $invoice) }}">
                @csrf
                <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Approve') }}</button>
            </form>
            <button type="button" onclick="document.getElementById('invoice-reject-form').classList.toggle('hidden')" class="rounded-lg border border-red-200 text-red-600 px-4 py-2 text-sm font-semibold hover:bg-red-50">{{ __('Reject') }}</button>
        @endif
        {{-- Editable any time it isn't yet part of an immutable ZATCA tax record, not just while still a draft. --}}
        @if ($invoice->status !== 'cancelled' && ! $invoice->isZatcaLocked())
            <a href="{{ route('app.invoices.edit', $invoice) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Edit') }}</a>
        @endif
        @if (in_array($invoice->status, ['sent', 'partially_paid', 'paid', 'overdue']) && $invoice->remainingCreditableTotal() > 0.01)
            <a href="{{ route('app.credit-notes.create') }}?invoice_id={{ $invoice->id }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Issue credit note') }}</a>
        @endif
        @if (in_array($invoice->status, ['sent', 'partially_paid', 'paid', 'overdue']))
            <a href="{{ route('app.debit-notes.create') }}?invoice_id={{ $invoice->id }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Issue debit note') }}</a>
        @endif
        @if (! in_array($invoice->status, ['draft', 'cancelled']) && ! $invoice->isZatcaLocked())
            <form method="POST" action="{{ route('app.invoices.cancel', $invoice) }}" onsubmit="return confirm('{{ __('Cancel this invoice?') }}')">
                @csrf
                <button type="submit" class="rounded-lg border border-red-200 px-4 py-2 text-sm font-semibold text-red-600 hover:border-red-300">{{ __('Cancel invoice') }}</button>
            </form>
        @endif
        @if ($invoice->isZatcaLocked())
            <a href="{{ route('app.invoices.xml', $invoice) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download XML') }}</a>
        @elseif (auth()->user()->hasPermission('zatca') && $invoice->company->hasFeature('zatca_phase2') && $invoice->company->zatcaIntegrationMode() === \App\Models\Company::ZATCA_MODE_PHASE2)
            @php
                $zatcaBlockedReason = $invoice->zatcaSyncBlockedReason();
            @endphp
            @if (is_null($zatcaBlockedReason))
                <form method="POST" action="{{ route('app.zatca.sync.invoice', $invoice) }}">
                    @csrf
                    <button type="submit" class="rounded-lg border border-brand-200 bg-brand-50 px-4 py-2 text-sm font-semibold text-brand-700 hover:border-brand-300">{{ __('Sync with ZATCA') }}</button>
                </form>
            @elseif ($invoice->status !== 'draft' && $invoice->status !== 'cancelled')
                <span class="inline-flex items-center rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-400" title="{{ $zatcaBlockedReason }}">{{ __('ZATCA sync unavailable') }}</span>
            @endif
        @endif
        <a href="{{ route('app.invoices.pdf', $invoice) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download PDF') }}</a>
        @if ($invoice->status !== 'draft')
            <button type="button" x-data="{ copied: false }" @click="navigator.clipboard.writeText('{{ route('public.invoices.show', ['id' => $invoice->id, 'token' => $invoice->public_token]) }}'); copied = true; setTimeout(() => copied = false, 2000)" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">
                <span x-show="!copied">{{ __('Copy payable link') }}</span>
                <span x-show="copied" style="display: none;">{{ __('Link copied!') }}</span>
            </button>
        @endif
        @if ($invoice->client->email)
            <form method="POST" action="{{ route('app.invoices.email', $invoice) }}">
                @csrf
                <button type="submit" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Email to client') }}</button>
            </form>
        @endif
        @if ($whatsappEnabled && ($invoice->client->mobile || $invoice->client->phone))
            <form method="POST" action="{{ route('app.invoices.whatsapp', $invoice) }}">
                @csrf
                <button type="submit" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Send via WhatsApp') }}</button>
            </form>
        @endif
        @if ($smsEnabled && ($invoice->client->mobile || $invoice->client->phone))
            <form method="POST" action="{{ route('app.invoices.sms', $invoice) }}">
                @csrf
                <button type="submit" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Send via SMS') }}</button>
            </form>
        @endif
        <button onclick="window.print()" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Print / PDF') }}</button>
    </div>
</div>

@if ($invoice->status === 'pending_approval' && auth()->user()->hasPermission('approvals'))
    <form id="invoice-reject-form" method="POST" action="{{ route('app.invoices.reject', $invoice) }}" class="hidden mb-6 max-w-md rounded-xl border border-red-100 bg-red-50 p-4 print:hidden">
        @csrf
        <label class="block text-xs font-medium text-red-700 mb-1">{{ __('Reason for rejection (optional)') }}</label>
        <textarea name="rejection_reason" rows="2" class="w-full rounded-lg border border-red-200 text-sm mb-3"></textarea>
        <button type="submit" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">{{ __('Confirm rejection') }}</button>
    </form>
@endif
@if ($invoice->status === 'draft' && $invoice->rejection_reason)
    <div class="mb-6 rounded-xl border border-red-100 bg-red-50 p-4 text-sm text-red-700 print:hidden">
        <span class="font-semibold">{{ __('Rejection reason:') }}</span> {{ $invoice->rejection_reason }}
    </div>
@endif

@php
    $doc = [
        'type_label' => $invoice->type === 'simplified' ? __('Simplified tax invoice') : __('Standard tax invoice'),
        'type_label_ar' => $invoice->type === 'simplified' ? 'فاتورة ضريبية مبسّطة' : 'فاتورة ضريبية عادية',
        'number' => $invoice->invoice_number,
        'date_label' => __('Issued'),
        'date' => $invoice->issue_date,
        'date2_label' => __('Due'),
        'date2_label_ar' => 'الاستحقاق',
        'date2' => $invoice->due_date,
        'party_label' => __('Bill to'),
        'party_label_ar' => 'العميل',
        'party' => $invoice->client,
        'qr_code' => $invoice->qr_code,
        'zatca_status' => $invoice->zatcaInvoiceLogs()->whereIn('status', ['cleared', 'reported'])->latest('id')->value('status'),
        'lines' => $invoice->items,
        'currency' => $invoice->currency,
        'subtotal' => $invoice->subtotal,
        'discount_total' => $invoice->discount_total,
        'vat_total' => $invoice->vat_total,
        'total' => $invoice->total,
        'extra_rows' => array_values(array_filter([
            $invoice->currency !== $invoice->company->currency ? [
                'label' => __(':currency equivalent (rate :rate)', ['currency' => $invoice->company->currency, 'rate' => rtrim(rtrim(number_format($invoice->exchange_rate, 6), '0'), '.')]),
                'value' => round($invoice->total * $invoice->exchange_rate, 2),
                'currency' => $invoice->company->currency,
            ] : null,
            $invoice->retention_amount > 0 ? [
                'label' => __('Retention held').' ('.rtrim(rtrim(number_format($invoice->retention_rate, 2), '0'), '.').'%)',
                'value' => $invoice->retention_amount,
            ] : null,
            ['label' => __('Paid'), 'value' => $invoice->amount_paid],
            [
                'label' => __('Balance due'), 'value' => $invoice->balanceDue(), 'emphasis' => true,
                'variant' => $invoice->balanceDue() > 0 ? 'red' : null,
            ],
        ])),
        'bank_account' => $invoice->bankAccount ?? $invoice->company->defaultBankAccount(),
        'salesperson' => $invoice->salesperson,
        'notes' => $invoice->notes,
    ];
@endphp
<div class="bg-white rounded-xl border border-slate-100 p-8 print:border-0 print:shadow-none">
    @include('documents.print.body', ['doc' => $doc, 'company' => $invoice->company, 'template' => $template])
</div>

<div class="mt-6 bg-white rounded-xl border border-slate-100 p-6 print:hidden">
    <h3 class="font-semibold text-slate-900 mb-4">{{ __('Payments') }}</h3>

    @if ($invoice->invoicePayments->isNotEmpty())
        <table class="w-full text-sm mb-6">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="py-2">{{ __('Date') }}</th>
                    <th class="py-2">{{ __('Method') }}</th>
                    <th class="py-2">{{ __('Reference') }}</th>
                    <th class="py-2 text-end">{{ __('Amount') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($invoice->invoicePayments as $payment)
                    <tr class="border-b border-slate-50 last:border-0">
                        <td class="py-2">{{ $payment->paid_at->format('Y-m-d') }}</td>
                        <td class="py-2">{{ $payment->method ?: '—' }}</td>
                        <td class="py-2">{{ $payment->reference ?: '—' }}</td>
                        <td class="py-2 text-end">{{ \App\Support\Money::format($payment->amount) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if ($invoice->balanceDue() > 0)
        <form method="POST" action="{{ route('app.invoices.payments.store', $invoice) }}" class="flex flex-wrap items-end gap-3">
            @csrf
            <div>
                <label class="block text-xs font-medium text-slate-500">{{ __('Amount') }}</label>
                <input type="number" step="0.01" min="0.01" max="{{ $invoice->balanceDue() }}" name="amount" value="{{ $invoice->balanceDue() }}" required class="mt-1 w-32 rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500">{{ __('Date') }}</label>
                <input type="date" name="paid_at" value="{{ now()->toDateString() }}" required class="mt-1 rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500">{{ __('Method') }}</label>
                <select name="method" class="mt-1 rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                    <option value="cash">{{ __('Cash') }}</option>
                    <option value="bank_transfer">{{ __('Bank transfer') }}</option>
                    <option value="card">{{ __('Card') }}</option>
                    <option value="other">{{ __('Other') }}</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500">{{ __('Reference') }}</label>
                <input type="text" name="reference" class="mt-1 rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
            </div>
            @if ($invoice->currency !== $invoice->company->currency)
                <div>
                    <label class="block text-xs font-medium text-slate-500">{{ __('Exchange rate today') }}</label>
                    <input type="number" step="0.000001" min="0.000001" name="exchange_rate" value="{{ $invoice->exchange_rate }}" class="mt-1 w-28 rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                    <p class="text-[11px] text-slate-400 mt-1">{{ __('Defaults to the invoice\'s own rate — change it only if today\'s rate is different, to record the FX gain/loss.') }}</p>
                </div>
            @endif
            <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Record payment') }}</button>
        </form>
    @endif
</div>

@if ($invoice->status !== 'cancelled')
    <div class="mt-6 bg-white rounded-xl border border-slate-100 p-6 print:hidden" x-data="{ editing: {{ $invoice->installments->isEmpty() ? 'true' : 'false' }} }">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-semibold text-slate-900">{{ __('Payment schedule') }}</h3>
            @if ($invoice->installments->isNotEmpty())
                <div class="flex items-center gap-3 text-sm">
                    <button type="button" @click="editing = ! editing" class="font-semibold text-brand-700 hover:underline" x-text="editing ? '{{ __('Cancel') }}' : '{{ __('Edit schedule') }}'"></button>
                    <form method="POST" action="{{ route('app.invoices.installments.destroy', $invoice) }}" onsubmit="return confirm('{{ __('Remove the payment schedule?') }}')">
                        @csrf @method('DELETE')
                        <button type="submit" class="font-semibold text-red-600 hover:underline">{{ __('Remove') }}</button>
                    </form>
                </div>
            @endif
        </div>

        @if ($invoice->installments->isNotEmpty())
            <table class="w-full text-sm mb-2" x-show="! editing">
                <thead>
                    <tr class="text-left text-slate-500 border-b border-slate-100">
                        <th class="py-2">{{ __('Description') }}</th>
                        <th class="py-2">{{ __('Due date') }}</th>
                        <th class="py-2 text-end">{{ __('Amount') }}</th>
                        <th class="py-2 text-end">{{ __('Paid') }}</th>
                        <th class="py-2 text-end">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($invoice->installmentSchedule() as $row)
                        <tr class="border-b border-slate-50 last:border-0">
                            <td class="py-2">{{ $row->installment->description ?: __('Installment') }}</td>
                            <td class="py-2">{{ $row->installment->due_date->format('Y-m-d') }}</td>
                            <td class="py-2 text-end">{{ \App\Support\Money::format($row->installment->amount) }}</td>
                            <td class="py-2 text-end">{{ \App\Support\Money::format($row->paid_amount) }}</td>
                            <td class="py-2 text-end">
                                <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-semibold {{ $row->status === 'paid' ? 'bg-emerald-50 text-emerald-700' : ($row->status === 'partial' ? 'bg-amber-50 text-amber-700' : 'bg-slate-100 text-slate-500') }}">
                                    {{ $row->status === 'paid' ? __('Paid') : ($row->status === 'partial' ? __('Partial') : __('Pending')) }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p class="text-sm text-slate-500 mb-4" x-show="! editing">{{ __('No payment schedule set — the full balance is due as usual.') }}</p>
        @endif

        <div x-show="editing" x-cloak>
            <p class="text-sm text-slate-500 mb-3">{{ __('Split the invoice total (:total) into a deposit/balance or a series of scheduled payments. The amounts must add up to the total.', ['total' => \App\Support\Money::format($invoice->total)]) }}</p>
            <form method="POST" action="{{ route('app.invoices.installments.store', $invoice) }}" id="installments-form">
                @csrf
                <table class="w-full text-sm mb-3">
                    <thead>
                        <tr class="text-left text-slate-500 border-b border-slate-100">
                            <th class="py-2 pe-3">{{ __('Description') }}</th>
                            <th class="py-2 pe-3 w-40">{{ __('Due date') }}</th>
                            <th class="py-2 pe-3 w-32">{{ __('Amount') }}</th>
                            <th class="py-2 w-8"></th>
                        </tr>
                    </thead>
                    <tbody id="installments-body"></tbody>
                </table>
                <button type="button" id="add-installment-row" class="text-sm font-semibold text-brand-700 hover:underline">{{ __('+ Add row') }}</button>
                <div class="mt-3 flex items-center justify-between">
                    <p class="text-sm" id="installments-sum-check"></p>
                    <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Save schedule') }}</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    (function () {
        const body = document.getElementById('installments-body');
        if (! body) return;

        const CURRENCY_SYMBOL = '{{ \App\Support\Money::symbol() }}';
        const INVOICE_TOTAL = {{ (float) $invoice->total }};
        const EXISTING = {!! $invoice->installments->map(fn ($i) => ['description' => $i->description, 'due_date' => $i->due_date->format('Y-m-d'), 'amount' => (float) $i->amount])->values()->toJson() !!};
        let rowIndex = 0;

        function addRow(data) {
            data = data || { description: '', due_date: '', amount: '' };
            const i = rowIndex++;
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="py-2 pe-3"><input type="text" name="installments[${i}][description]" value="${data.description || ''}" placeholder="{{ __('e.g. Deposit') }}" class="w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500"></td>
                <td class="py-2 pe-3"><input type="date" name="installments[${i}][due_date]" value="${data.due_date}" required class="w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500"></td>
                <td class="py-2 pe-3"><input type="number" step="0.01" min="0.01" name="installments[${i}][amount]" data-role="amount" value="${data.amount}" required class="w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500"></td>
                <td class="py-2"><button type="button" data-role="remove" class="text-slate-400 hover:text-red-600">&times;</button></td>
            `;
            body.appendChild(tr);
        }

        function checkSum() {
            const sum = [...body.querySelectorAll('[data-role="amount"]')].reduce((s, el) => s + (parseFloat(el.value) || 0), 0);
            const el = document.getElementById('installments-sum-check');
            const diff = Math.round((sum - INVOICE_TOTAL) * 100) / 100;
            el.textContent = CURRENCY_SYMBOL + ' ' + sum.toFixed(2) + ' / ' + CURRENCY_SYMBOL + ' ' + INVOICE_TOTAL.toFixed(2);
            el.className = 'text-sm ' + (Math.abs(diff) < 0.01 ? 'text-emerald-600' : 'text-red-600');
        }

        body.addEventListener('input', checkSum);
        body.addEventListener('click', (e) => {
            if (e.target.closest('[data-role="remove"]')) {
                e.target.closest('tr').remove();
                checkSum();
            }
        });
        document.getElementById('add-installment-row').addEventListener('click', () => { addRow(); checkSum(); });

        if (EXISTING.length) {
            EXISTING.forEach(addRow);
        } else {
            addRow({ description: '{{ __('Deposit') }}', due_date: '{{ now()->toDateString() }}', amount: (INVOICE_TOTAL / 2).toFixed(2) });
            addRow({ description: '{{ __('Balance') }}', due_date: '{{ now()->addDays(30)->toDateString() }}', amount: (INVOICE_TOTAL / 2).toFixed(2) });
        }
        checkSum();
    })();
    </script>
@endif

<div class="mt-6 bg-white rounded-xl border border-slate-100 p-6 print:hidden">
    <div class="flex items-center justify-between mb-4">
        <h3 class="font-semibold text-slate-900">{{ __('Attachments') }}</h3>
        <button type="button" onclick="document.getElementById('attach-file-input').click()" class="text-sm font-semibold text-brand-700 hover:underline">{{ __('+ Attach file') }}</button>
        <form method="POST" action="{{ route('app.invoices.attachments.store', $invoice) }}" enctype="multipart/form-data" id="attach-file-form" class="hidden">
            @csrf
            <input type="file" name="file" id="attach-file-input" onchange="document.getElementById('attach-file-form').submit()">
        </form>
    </div>

    @if ($invoice->attachments->isEmpty())
        <p class="text-sm text-slate-400">{{ __('No attachments') }}</p>
    @else
        <ul class="divide-y divide-slate-50">
            @foreach ($invoice->attachments as $attachment)
                <li class="flex items-center justify-between py-2 text-sm">
                    <a href="{{ Storage::url($attachment->path) }}" target="_blank" class="text-brand-700 hover:underline">{{ $attachment->original_name }}</a>
                    <div class="flex items-center gap-3 text-slate-400">
                        <span>{{ $attachment->humanSize() }}</span>
                        <form method="POST" action="{{ route('app.invoices.attachments.destroy', [$invoice, $attachment]) }}" onsubmit="return confirm('{{ __('Remove this attachment?') }}')">
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
