{{--
    On-screen/browser-print equivalent of pdf-payments-received.blade.php
    (the mPDF download path's compact "Payments received" table) — same
    method label mapping, rendered with Tailwind instead of mPDF tables.
    $primary/$secondary are passed in explicitly since Blade includes
    don't inherit the parent's own local variables.
--}}
@php
    $paymentMethodLabels = [
        'cash' => ['en' => 'Cash', 'ar' => 'نقدًا'],
        'bank_transfer' => ['en' => 'Bank transfer', 'ar' => 'تحويل بنكي'],
        'card' => ['en' => 'Card', 'ar' => 'بطاقة'],
        'other' => ['en' => 'Other', 'ar' => 'أخرى'],
    ];
@endphp
<div class="mt-4 rounded-2xl border border-slate-100 overflow-hidden text-sm">
    <div class="px-4 py-2 text-xs font-semibold uppercase tracking-wide text-slate-400 bg-slate-50">
        {{ $primary('Payments received', 'الدفعات المستلمة') }}
        @if ($secondary('الدفعات المستلمة')) <span dir="rtl">/ الدفعات المستلمة</span> @endif
    </div>
    @foreach ($payments as $payment)
        @php
            $methodKey = $payment->method ? strtolower($payment->method) : null;
            $methodLabel = $methodKey && isset($paymentMethodLabels[$methodKey])
                ? $primary($paymentMethodLabels[$methodKey]['en'], $paymentMethodLabels[$methodKey]['ar'])
                : ($payment->method ?: $primary('—'));
        @endphp
        <div class="flex items-center justify-between px-4 py-2 border-t border-slate-100">
            <span class="text-slate-500">{{ $payment->paid_at->format('Y-m-d') }}</span>
            <span class="text-slate-500">{{ $methodLabel }}</span>
            <span class="font-semibold text-emerald-600">{{ \App\Support\Money::format($payment->amount) }}</span>
        </div>
    @endforeach
</div>
