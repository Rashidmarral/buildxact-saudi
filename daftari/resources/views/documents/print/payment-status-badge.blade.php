{{--
    On-screen/public counterpart to pdf-payment-status-badge.blade.php (that
    one uses mPDF-safe inline styles; this one is a normal Tailwind pill) —
    shown in the document header, not buried near the totals box, so a
    client opening the public "view & pay" link or anyone viewing it
    on-screen sees Paid/Pending/Partially Paid/Overdue at a glance without
    reading the itemized payment history (which is Settings-only — see
    InvoiceController's own dedicated "Payments" panel, never part of this
    shared document body).
--}}
@php
    $paymentBadge = match ($doc['payment_status'] ?? null) {
        'pending' => ['class' => 'bg-sky-50 text-sky-700', 'en' => 'Pending', 'ar' => 'قيد الانتظار'],
        'partially_paid' => ['class' => 'bg-amber-50 text-amber-700', 'en' => 'Partially Paid', 'ar' => 'مدفوعة جزئيًا'],
        'paid' => ['class' => 'bg-emerald-50 text-emerald-700', 'en' => 'Paid', 'ar' => 'مدفوعة بالكامل'],
        'overdue' => ['class' => 'bg-red-50 text-red-700', 'en' => 'Overdue', 'ar' => 'متأخرة السداد'],
        default => null,
    };
@endphp
@if ($paymentBadge)
    <div class="mt-2 flex justify-center">
        <span class="inline-block rounded-full px-3 py-1 text-xs font-semibold {{ $paymentBadge['class'] }}">
            {{ $primary($paymentBadge['en'], $paymentBadge['ar']) }}
            @if ($secondary($paymentBadge['ar'])) — {{ $paymentBadge['ar'] }} @endif
        </span>
    </div>
@endif
