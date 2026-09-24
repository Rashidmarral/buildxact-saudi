{{--
    On-screen rendering of a POS/Restaurant sale receipt, shared by the
    show page (user.pos.receipt) and, in spirit, the PDF sibling
    (pos-receipt-pdf — rebuilt with plain HTML/CSS there since mPDF's
    renderer doesn't support Tailwind's flex/grid utilities). Branches on
    $layout ('receipt_compact' | 'receipt_detailed') and $languageMode,
    matching the invoice/quotation template system's own convention —
    see ReceiptTemplatePresets and ReceiptTemplateController.
--}}
@php
    $languageMode = $languageMode ?? 'bilingual';
    // Every label goes through this one closure so bilingual mode is
    // consistent everywhere (English / Arabic on one line) rather than
    // only some rows getting a second language — item names themselves
    // (real product names, not UI labels) are untouched.
    $lbl = function (string $key) use ($languageMode) {
        $en = \Illuminate\Support\Facades\Lang::get($key, [], 'en');
        $ar = \Illuminate\Support\Facades\Lang::get($key, [], 'ar');

        return match ($languageMode) {
            'english_only' => $en,
            'arabic_only' => $ar,
            default => $en.' / '.$ar,
        };
    };
    $isDetailed = ($layout ?? 'receipt_compact') === 'receipt_detailed';
@endphp
<div class="max-w-md mx-auto bg-white rounded-xl border border-slate-100 p-6 font-mono text-sm" @if($languageMode === 'arabic_only') dir="rtl" @endif>
    <div class="text-center mb-4">
        @if ($isDetailed && ($sale->company->logo_path ?? null))
            <img src="{{ Illuminate\Support\Facades\Storage::disk('public')->url($sale->company->logo_path) }}" alt="" class="mx-auto h-12 mb-2 object-contain">
        @endif
        <p class="text-base font-bold text-slate-900">{{ $sale->company->name }}</p>
        @if ($isDetailed)
            @if ($sale->company->address)<p class="text-xs text-slate-500">{{ $sale->company->address }}</p>@endif
            @if ($sale->company->phone)<p class="text-xs text-slate-500">{{ $sale->company->phone }}</p>@endif
        @endif
        @if ($sale->company->vat_number)
            <p class="text-xs text-slate-500">{{ $lbl('VAT') }}: {{ $sale->company->vat_number }}</p>
        @endif
        <p class="text-xs text-slate-500 mt-1">{{ $sale->register->name }}</p>
        <p class="text-xs text-slate-500">{{ $sale->created_at->format('Y-m-d H:i') }}</p>
        <p class="text-xs text-slate-500">{{ $lbl('Receipt') }}: {{ $sale->sale_number }}</p>
        @if ($sale->client)
            <p class="text-xs text-slate-500">{{ $lbl('Client') }}: {{ $sale->client->name }}</p>
        @endif
    </div>

    <div class="border-t border-b border-dashed border-slate-300 py-3 space-y-2">
        @foreach ($sale->items as $line)
            <div class="flex justify-between gap-2">
                <span class="text-slate-800">{{ $line->item->name ?? $line->description }}</span>
            </div>
            <div class="flex justify-between text-xs text-slate-500">
                <span>{{ rtrim(rtrim(number_format($line->quantity, 3), '0'), '.') }} × {{ number_format($line->unit_price, 2) }}</span>
                <span class="tabular-nums">{{ number_format($line->line_total, 2) }}</span>
            </div>
            @if ($isDetailed && (float) $line->vat_rate > 0)
                <div class="flex justify-between text-[10px] text-slate-400">
                    <span>{{ $lbl('VAT') }} {{ rtrim(rtrim(number_format($line->vat_rate, 2), '0'), '.') }}%</span>
                    <span class="tabular-nums">{{ number_format($line->vat_amount, 2) }}</span>
                </div>
            @endif
        @endforeach
    </div>

    <div class="pt-3 space-y-1">
        <div class="flex justify-between text-slate-500"><span>{{ $lbl('Subtotal') }}</span><span class="tabular-nums">{{ number_format($sale->subtotal, 2) }}</span></div>
        @if ($sale->discount_total > 0)
            <div class="flex justify-between text-slate-500"><span>{{ $lbl('Discount') }}</span><span class="tabular-nums">-{{ number_format($sale->discount_total, 2) }}</span></div>
        @endif
        <div class="flex justify-between text-slate-500"><span>{{ $lbl('VAT') }}</span><span class="tabular-nums">{{ number_format($sale->vat_total, 2) }}</span></div>
        <div class="flex justify-between text-base font-bold text-slate-900 pt-2 border-t border-dashed border-slate-300">
            <span>{{ $lbl('Total') }}</span><span class="tabular-nums">{{ number_format($sale->total, 2) }}</span>
        </div>
    </div>

    <div class="pt-3 mt-3 border-t border-dashed border-slate-300 space-y-1">
        <p class="text-xs font-semibold uppercase text-slate-400">{{ $lbl('Payments') }}</p>
        @foreach ($sale->payments as $payment)
            <div class="flex justify-between text-slate-600">
                <span class="capitalize">{{ __(ucfirst($payment->method)) }}</span>
                <span class="tabular-nums">{{ number_format($payment->amount, 2) }}</span>
            </div>
        @endforeach
    </div>

    <div class="mt-5 text-center">
        <img src="data:image/png;base64,{{ $qr }}" alt="{{ __('ZATCA QR code') }}" class="mx-auto h-36 w-36" onerror="this.style.display='none'">
        <p class="mt-1 text-xs text-slate-400">{{ $lbl('Scan to verify sale details') }}</p>
        @if ($isDetailed)
            <p class="mt-2 text-xs text-slate-400">{{ $lbl('Thank you for your business.') }}</p>
        @endif
    </div>
</div>
