{{--
    Shared printable body for Receipt and Payment Vouchers, used by both
    show.blade.php (browser view/print) and the standalone PDF wrapper —
    keeps the two renders identical. $type is 'receipt' or 'payment'.

    Modeled on the classic bilingual "سند صرف" / "Payment Voucher" form
    Saudi companies print for bank/cheque clearance: a bordered box with
    English fields on the left, their Arabic mirror on the right (the
    same left-English/right-Arabic convention documents.print.bilingual-
    header already uses), an amount spelled out in words on both sides,
    a cash/cheque checkbox pair, and two signature blocks.
--}}
@php
    $isReceipt = $type === 'receipt';
    $partyNameEn = $isReceipt ? $voucher->payer_name : $voucher->payee_name;
    $partyNameAr = $voucher->party_name_ar;
    $isCheque = $voucher->method === 'cheque';

    $purpose = $voucher->notes ?: $voucher->defaultPurpose();

    $amountWordsEn = \App\Support\NumberToWords::englishRiyals((float) $voucher->amount);
    $amountWordsAr = \App\Support\NumberToWords::arabicRiyals((float) $voucher->amount);
@endphp
<div class="relative border-2 border-slate-800 rounded-xl mt-4 p-6" style="page-break-inside: avoid;">
    @if ($template && $template->watermark_path)
        <img src="{{ Storage::url($template->watermark_path) }}" alt="" class="pointer-events-none select-none absolute inset-0 m-auto max-w-[70%] max-h-[70%] object-contain" style="opacity: {{ ($template->watermark_opacity ?? 10) / 100 }}; z-index: 0;">
    @endif
    <div class="relative" style="z-index: 1;">
    <div class="flex items-center justify-between border-b border-slate-200 pb-3">
        <div class="text-xs text-slate-500">
            <div>{{ __('No.') }}: <span class="font-semibold text-slate-800">{{ $voucher->voucher_number }}</span></div>
            <div>{{ __('Date') }}: <span class="font-semibold text-slate-800">{{ \App\Support\PlatformFormat::date($voucher->date) }}</span></div>
        </div>
        <div class="text-center">
            <h2 class="text-lg font-bold text-slate-900">{{ $isReceipt ? __('Receipt Voucher') : __('Payment Voucher') }}</h2>
            <p class="text-sm font-semibold text-slate-500" dir="rtl">{{ $isReceipt ? 'سند قبض' : 'سند صرف' }}</p>
        </div>
        <div class="text-end text-xs text-slate-500">
            <div class="text-lg font-bold text-slate-900">{{ \App\Support\Money::format($voucher->amount) }}</div>
            <div dir="rtl">{{ __('SAR') }}</div>
        </div>
    </div>

    <div class="mt-5 space-y-4 text-sm">
        <div class="flex items-start justify-between gap-4 flex-wrap">
            <div class="flex-1 min-w-0">
                <span class="text-slate-400">{{ $isReceipt ? __('Received from') : __('Payment To') }}:</span>
                <span class="font-semibold text-slate-800">{{ $partyNameEn }}</span>
            </div>
            <div class="flex-1 min-w-0 text-end" dir="rtl">
                <span class="text-slate-400">{{ $isReceipt ? 'استلمنا من' : 'أصرفوا للمكرم' }}:</span>
                <span class="font-semibold text-slate-800">{{ $partyNameAr ?: $partyNameEn }}</span>
            </div>
        </div>

        @if ($voucher->party_vat_number)
            <div class="flex items-start justify-between gap-4 flex-wrap">
                <div class="flex-1 min-w-0"><span class="text-slate-400">{{ __('VAT number') }}:</span> <span class="font-medium text-slate-800">{{ $voucher->party_vat_number }}</span></div>
                <div class="flex-1 min-w-0 text-end" dir="rtl"><span class="text-slate-400">الرقم الضريبي:</span> <span class="font-medium text-slate-800">{{ $voucher->party_vat_number }}</span></div>
            </div>
        @endif

        <div class="flex items-start justify-between gap-4 flex-wrap border-t border-dashed border-slate-200 pt-3">
            <div class="flex-1 min-w-0">
                <span class="text-slate-400">{{ __('The Sum of') }}:</span>
                <span class="font-semibold text-slate-800">{{ $amountWordsEn }}</span>
            </div>
            <div class="flex-1 min-w-0 text-end" dir="rtl">
                <span class="text-slate-400">مبلغاً وقدره فقط:</span>
                <span class="font-semibold text-slate-800">{{ $amountWordsAr }}</span>
            </div>
        </div>

        <div class="flex items-center flex-wrap gap-x-8 gap-y-2 border-t border-dashed border-slate-200 pt-3 text-xs">
            <label class="flex items-center gap-1.5">
                <span class="inline-flex h-4 w-4 items-center justify-center rounded border {{ $voucher->method === 'cash' ? 'border-slate-800 bg-slate-800 text-white' : 'border-slate-300' }}">{{ $voucher->method === 'cash' ? '✓' : '' }}</span>
                {{ __('Cash') }} <span dir="rtl">نقداً</span>
            </label>
            <label class="flex items-center gap-1.5">
                <span class="inline-flex h-4 w-4 items-center justify-center rounded border {{ $isCheque ? 'border-slate-800 bg-slate-800 text-white' : 'border-slate-300' }}">{{ $isCheque ? '✓' : '' }}</span>
                {{ __('Cheque') }} <span dir="rtl">بشيك</span>
            </label>
            @if ($isCheque)
                <span>{{ __('No.') }}: <span class="font-semibold text-slate-800">{{ $voucher->reference ?: '—' }}</span></span>
                <span>{{ __('Bank') }}: <span class="font-semibold text-slate-800">{{ $voucher->bankAccount->bank_name ?: $voucher->bankAccount->name }}</span></span>
            @else
                <span>{{ __('Account') }}: <span class="font-semibold text-slate-800">{{ $voucher->bankAccount->name }}</span></span>
            @endif
        </div>

        @if ($purpose)
            <div class="flex items-start justify-between gap-4 flex-wrap border-t border-dashed border-slate-200 pt-3">
                <div class="flex-1 min-w-0"><span class="text-slate-400">{{ __('For') }}:</span> <span class="font-medium text-slate-800">{{ $purpose }}</span></div>
                <div class="flex-1 min-w-0 text-end" dir="rtl"><span class="text-slate-400">وذلك مقابل:</span> <span class="font-medium text-slate-800">{{ $purpose }}</span></div>
            </div>
        @endif

        @if ($voucher->counterAccount)
            <div class="text-xs text-slate-400 border-t border-dashed border-slate-200 pt-3">{{ __('Counter account') }}: <span class="font-medium text-slate-600">{{ $voucher->counterAccount->label() }}</span></div>
        @endif
        @if (! $isReceipt && (float) $voucher->wht_amount > 0)
            <div class="text-xs text-slate-400">{{ __('Withholding tax deducted') }}: <span class="font-medium text-slate-600">{{ \App\Support\Money::format($voucher->wht_amount) }}</span></div>
        @endif
        @if ($voucher->reference && ! $isCheque)
            <div class="text-xs text-slate-400">{{ __('Reference') }}: <span class="font-medium text-slate-600">{{ $voucher->reference }}</span></div>
        @endif
    </div>

    <div class="mt-10 pt-6 border-t border-slate-200 grid grid-cols-2 gap-6 text-center">
        <div>
            <div class="h-10 border-b border-slate-400"></div>
            <p class="mt-2 text-xs text-slate-500">{{ __('Accountant') }} <span dir="rtl">المحاسب</span></p>
        </div>
        <div>
            <div class="h-10 border-b border-slate-400"></div>
            <p class="mt-2 text-xs text-slate-500">{{ __('Receiver') }} <span dir="rtl">توقيع المستلم</span></p>
        </div>
    </div>
    </div>
</div>
