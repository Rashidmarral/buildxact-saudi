@extends('layouts.app')

@section('title', $quotation->quotation_number)

@section('content')
<div class="flex items-center justify-between mb-6 print:hidden">
    <div class="flex items-center gap-3">
        <h2 class="text-lg font-semibold text-slate-900">{{ $quotation->quotation_number }}</h2>
        @include('user.quotations.partials.status-badge', ['status' => $quotation->status])
    </div>
    <div class="flex items-center gap-3">
        @if ($quotation->status === 'draft')
            <form method="POST" action="{{ route('app.quotations.send', $quotation) }}">
                @csrf
                <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Mark as issued') }}</button>
            </form>
        @endif
        @if ($quotation->status === 'pending_approval' && auth()->user()->hasPermission('approvals'))
            <form method="POST" action="{{ route('app.quotations.approve-issuance', $quotation) }}">
                @csrf
                <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Approve') }}</button>
            </form>
            <button type="button" onclick="document.getElementById('quotation-reject-issuance-form').classList.toggle('hidden')" class="rounded-lg border border-red-200 text-red-600 px-4 py-2 text-sm font-semibold hover:bg-red-50">{{ __('Reject') }}</button>
        @endif
        {{-- Editable any time it hasn't been converted into a real invoice yet — not just while still a draft. --}}
        @if ($quotation->status !== 'converted')
            <a href="{{ route('app.quotations.edit', $quotation) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Edit') }}</a>
        @endif
        @if (in_array($quotation->status, ['draft', 'issued']))
            <form method="POST" action="{{ route('app.quotations.accept', $quotation) }}">
                @csrf
                <button type="submit" class="rounded-lg border border-brand-200 text-brand-700 px-4 py-2 text-sm font-semibold hover:bg-brand-50">{{ __('Mark as accepted') }}</button>
            </form>
            <form method="POST" action="{{ route('app.quotations.reject', $quotation) }}">
                @csrf
                <button type="submit" class="rounded-lg border border-red-200 text-red-600 px-4 py-2 text-sm font-semibold hover:bg-red-50">{{ __('Mark as rejected') }}</button>
            </form>
        @endif
        @if ($quotation->status === 'accepted' && ! $quotation->is_staged)
            <form method="POST" action="{{ route('app.quotations.convert', $quotation) }}">
                @csrf
                <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Convert to invoice') }}</button>
            </form>
        @endif
        @if ($quotation->status === 'converted' && ! $quotation->is_staged && $quotation->convertedInvoice)
            <a href="{{ route('app.invoices.show', $quotation->convertedInvoice) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('View invoice') }}</a>
        @endif
        <a href="{{ route('app.quotations.pdf', $quotation) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download PDF') }}</a>
        @if ($quotation->client->email)
            <form method="POST" action="{{ route('app.quotations.email', $quotation) }}">
                @csrf
                <button type="submit" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Email to client') }}</button>
            </form>
        @endif
        <button onclick="window.print()" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Print / PDF') }}</button>
    </div>
</div>

@if ($quotation->status === 'pending_approval' && auth()->user()->hasPermission('approvals'))
    <form id="quotation-reject-issuance-form" method="POST" action="{{ route('app.quotations.reject-issuance', $quotation) }}" class="hidden mb-6 max-w-md rounded-xl border border-red-100 bg-red-50 p-4 print:hidden">
        @csrf
        <label class="block text-xs font-medium text-red-700 mb-1">{{ __('Reason for rejection (optional)') }}</label>
        <textarea name="approval_rejection_reason" rows="2" class="w-full rounded-lg border border-red-200 text-sm mb-3"></textarea>
        <button type="submit" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">{{ __('Confirm rejection') }}</button>
    </form>
@endif
@if ($quotation->status === 'draft' && $quotation->approval_rejection_reason)
    <div class="mb-6 rounded-xl border border-red-100 bg-red-50 p-4 text-sm text-red-700 print:hidden">
        <span class="font-semibold">{{ __('Rejection reason:') }}</span> {{ $quotation->approval_rejection_reason }}
    </div>
@endif

@if ($quotation->status === 'accepted' && $quotation->accepted_at)
    <div class="mb-6 rounded-xl border border-emerald-100 bg-emerald-50 p-4 text-sm text-emerald-800 print:hidden">
        <p class="font-semibold mb-2">{{ __('Acceptance record') }}</p>
        <p>{{ __('Accepted by :name on :date', ['name' => $quotation->accepted_by_name, 'date' => $quotation->accepted_at->format('Y-m-d H:i')]) }}</p>
        @if ($quotation->accepted_ip)
            <p class="text-emerald-700">{{ __('IP address: :ip', ['ip' => $quotation->accepted_ip]) }}</p>
        @endif
        @if ($quotation->accepted_signature)
            <img src="{{ $quotation->accepted_signature }}" alt="{{ __('Signature') }}" class="mt-2 h-20 rounded-lg border border-emerald-200 bg-white p-1">
        @endif
    </div>
@endif

@php
    $doc = [
        'type_label' => $quotation->type === 'proforma' ? __('Proforma Invoice') : __('Quotation'),
        'type_label_ar' => $quotation->type === 'proforma' ? 'فاتورة أولية' : 'عرض سعر',
        'number' => $quotation->quotation_number,
        'date_label' => __('Issued'),
        'date' => $quotation->issue_date,
        'date2_label' => __('Valid until'),
        'date2_label_ar' => 'صالح حتى',
        'date2' => $quotation->expiry_date,
        'party_label' => __('To'),
        'party_label_ar' => 'العميل',
        'party' => $quotation->client,
        'lines' => $quotation->items,
        'subtotal' => $quotation->subtotal,
        'discount_total' => $quotation->discount_total,
        'discount_percent' => $quotation->discount_type === 'percentage' ? $quotation->discount_value : null,
        'vat_total' => $quotation->vat_total,
        'total' => $quotation->total,
        'bank_account' => $quotation->bankAccount,
        'salesperson' => $quotation->salesperson,
        'notes' => $quotation->notes,
    ];
@endphp
<div class="bg-white rounded-xl border border-slate-100 p-8 print:border-0 print:shadow-none">
    @include('documents.print.body', ['doc' => $doc, 'company' => $quotation->company, 'template' => $template])
</div>

@if ($quotation->status === 'accepted' || $quotation->is_staged)
    <div class="mt-6 bg-white rounded-xl border border-slate-100 p-6 print:hidden">
        <div class="flex items-center justify-between mb-1">
            <h3 class="font-semibold text-slate-900">{{ __('Payment Plan') }}</h3>
            @if (! $quotation->is_staged)
                <button type="button" onclick="document.getElementById('payment-plan-builder').classList.remove('hidden'); this.classList.add('hidden')" class="text-sm font-semibold text-brand-700 hover:underline">{{ __('Bill this quotation in stages') }}</button>
            @elseif (! $quotation->paymentPlanIsLocked())
                <button type="button" onclick="document.getElementById('payment-plan-builder').classList.toggle('hidden')" class="text-sm font-semibold text-brand-700 hover:underline">{{ __('Edit plan') }}</button>
            @endif
        </div>
        <p class="text-sm text-slate-500 mb-4">{{ __('Split this quotation\'s total into stages — advance payment, then progress payments — and generate one invoice per stage whenever you\'re ready to bill it.') }}</p>

        @if ($quotation->is_staged)
            <div class="divide-y divide-slate-50 mb-4">
                @foreach ($quotation->paymentPlanStages as $stage)
                    <div class="flex items-center justify-between py-3">
                        <div>
                            <p class="text-sm font-medium text-slate-800">{{ $loop->iteration }}. {{ $stage->description }}</p>
                            <p class="text-xs text-slate-400">{{ rtrim(rtrim(number_format((float) $stage->percentage, 2), '0'), '.') }}% — {{ \App\Support\Money::format($stage->amount()) }}</p>
                        </div>
                        @if ($stage->invoice_id)
                            <a href="{{ route('app.invoices.show', $stage->invoice) }}" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:border-slate-300">{{ __('View invoice') }} ({{ $stage->invoice->invoice_number }})</a>
                        @else
                            <form method="POST" action="{{ route('app.quotations.payment-plan.generate-invoice', [$quotation, $stage]) }}">
                                @csrf
                                <button type="submit" class="rounded-lg bg-brand-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-brand-700">{{ __('Generate invoice') }}</button>
                            </form>
                        @endif
                    </div>
                @endforeach
            </div>
            @if ($quotation->isFullyStageInvoiced())
                <p class="text-sm font-medium text-emerald-700">{{ __('Every stage has been invoiced.') }}</p>
            @endif
        @endif

        @if (! $quotation->paymentPlanIsLocked())
            <form id="payment-plan-builder" method="POST" action="{{ route('app.quotations.payment-plan.store', $quotation) }}" class="{{ $quotation->is_staged ? 'hidden' : '' }} mt-2 rounded-xl border border-slate-100 bg-slate-50 p-4">
                @csrf
                @error('stages')
                    <p class="text-sm text-red-600 mb-3">{{ $message }}</p>
                @enderror
                <div id="stage-rows" class="space-y-2 mb-3">
                    @php($existingStages = $quotation->paymentPlanStages)
                    @forelse ($existingStages as $stage)
                        <div class="grid grid-cols-12 gap-2 items-center stage-row">
                            <input type="text" name="stages[][description]" value="{{ $stage->description }}" placeholder="{{ __('e.g. Advance payment') }}" class="col-span-7 rounded-lg border-slate-200 text-sm" required>
                            <div class="col-span-3 relative">
                                <input type="number" step="0.01" min="0.01" max="100" name="stages[][percentage]" value="{{ (float) $stage->percentage }}" oninput="updateStageTotal()" class="stage-percentage w-full rounded-lg border-slate-200 text-sm pe-6" required>
                                <span class="absolute end-2 top-2 text-xs text-slate-400">%</span>
                            </div>
                            <span class="col-span-1 text-xs text-slate-500 stage-amount">—</span>
                            <button type="button" onclick="this.closest('.stage-row').remove(); updateStageTotal()" class="col-span-1 text-red-500 hover:text-red-700 text-sm">✕</button>
                        </div>
                    @empty
                        <div class="grid grid-cols-12 gap-2 items-center stage-row">
                            <input type="text" name="stages[][description]" placeholder="{{ __('e.g. Advance payment') }}" class="col-span-7 rounded-lg border-slate-200 text-sm" required>
                            <div class="col-span-3 relative">
                                <input type="number" step="0.01" min="0.01" max="100" name="stages[][percentage]" oninput="updateStageTotal()" class="stage-percentage w-full rounded-lg border-slate-200 text-sm pe-6" required>
                                <span class="absolute end-2 top-2 text-xs text-slate-400">%</span>
                            </div>
                            <span class="col-span-1 text-xs text-slate-500 stage-amount">—</span>
                            <button type="button" onclick="this.closest('.stage-row').remove(); updateStageTotal()" class="col-span-1 text-red-500 hover:text-red-700 text-sm">✕</button>
                        </div>
                    @endforelse
                </div>
                <div class="flex items-center justify-between">
                    <button type="button" onclick="addStageRow()" class="text-sm font-semibold text-brand-700 hover:underline">{{ __('+ Add stage') }}</button>
                    <span id="stage-total" class="text-xs font-medium text-slate-500">{{ __('Total: 0%') }}</span>
                </div>
                <button type="submit" class="mt-4 rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Save payment plan') }}</button>
            </form>
        @endif
    </div>

    <script>
        const quotationTotal = {{ (float) $quotation->total }};

        function rowTemplate() {
            const row = document.createElement('div');
            row.className = 'grid grid-cols-12 gap-2 items-center stage-row';
            row.innerHTML = `
                <input type="text" name="stages[][description]" placeholder="{{ __('e.g. Advance payment') }}" class="col-span-7 rounded-lg border-slate-200 text-sm" required>
                <div class="col-span-3 relative">
                    <input type="number" step="0.01" min="0.01" max="100" name="stages[][percentage]" oninput="updateStageTotal()" class="stage-percentage w-full rounded-lg border-slate-200 text-sm pe-6" required>
                    <span class="absolute end-2 top-2 text-xs text-slate-400">%</span>
                </div>
                <span class="col-span-1 text-xs text-slate-500 stage-amount">—</span>
                <button type="button" onclick="this.closest('.stage-row').remove(); updateStageTotal()" class="col-span-1 text-red-500 hover:text-red-700 text-sm">✕</button>
            `;
            return row;
        }

        function addStageRow() {
            document.getElementById('stage-rows').appendChild(rowTemplate());
        }

        function updateStageTotal() {
            let total = 0;
            document.querySelectorAll('.stage-row').forEach((row) => {
                const pct = parseFloat(row.querySelector('.stage-percentage').value) || 0;
                total += pct;
                row.querySelector('.stage-amount').textContent = pct > 0
                    ? (quotationTotal * pct / 100).toLocaleString(undefined, { maximumFractionDigits: 0 })
                    : '—';
            });
            const label = document.getElementById('stage-total');
            if (label) {
                label.textContent = @js(__('Total:')) + ' ' + total.toFixed(2).replace(/\.?0+$/, '') + '%';
                label.className = 'text-xs font-medium ' + (Math.abs(total - 100) < 0.01 ? 'text-emerald-600' : 'text-amber-600');
            }
        }

        document.addEventListener('DOMContentLoaded', updateStageTotal);
    </script>
@endif

<div class="mt-6 bg-white rounded-xl border border-slate-100 p-6 print:hidden">
    <div class="flex items-center justify-between mb-4">
        <h3 class="font-semibold text-slate-900">{{ __('Attachments') }}</h3>
        <button type="button" onclick="document.getElementById('attach-file-input').click()" class="text-sm font-semibold text-brand-700 hover:underline">{{ __('+ Attach file') }}</button>
        <form method="POST" action="{{ route('app.quotations.attachments.store', $quotation) }}" enctype="multipart/form-data" id="attach-file-form" class="hidden">
            @csrf
            <input type="file" name="file" id="attach-file-input" onchange="document.getElementById('attach-file-form').submit()">
        </form>
    </div>

    @if ($quotation->attachments->isEmpty())
        <p class="text-sm text-slate-400">{{ __('No attachments') }}</p>
    @else
        <ul class="divide-y divide-slate-50">
            @foreach ($quotation->attachments as $attachment)
                <li class="flex items-center justify-between py-2 text-sm">
                    <a href="{{ Storage::url($attachment->path) }}" target="_blank" class="text-brand-700 hover:underline">{{ $attachment->original_name }}</a>
                    <div class="flex items-center gap-3 text-slate-400">
                        <span>{{ $attachment->humanSize() }}</span>
                        <form method="POST" action="{{ route('app.quotations.attachments.destroy', [$quotation, $attachment]) }}" onsubmit="return confirm('{{ __('Remove this attachment?') }}')">
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
