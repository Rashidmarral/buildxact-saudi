{{--
    A compact "Payments received" table — date, method, amount per
    InvoicePayment — shown as supporting detail underneath the totals
    box. The running "Paid"/"Balance due" figures in the totals box are
    still the primary numbers and always show regardless of whether this
    table renders; this only adds the "how/when it was paid" breakdown
    once there's at least one payment on record.
--}}
@php
    $paymentMethodLabels = [
        'cash' => ['en' => 'Cash', 'ar' => 'نقدًا'],
        'bank_transfer' => ['en' => 'Bank transfer', 'ar' => 'تحويل بنكي'],
        'card' => ['en' => 'Card', 'ar' => 'بطاقة'],
        'other' => ['en' => 'Other', 'ar' => 'أخرى'],
    ];
@endphp
<table style="margin-top: 10px; border: 0.5pt solid #e2e8f0; border-radius: 10px;">
    <tr>
        <td colspan="3" style="padding: 5px 10px; font-size: 8pt; font-weight: bold; text-transform: uppercase; color: #94a3b8; background-color: #f8fafc;">
            {{ $primary('Payments received', 'الدفعات المستلمة') }}
            @if ($secondary('الدفعات المستلمة')) / الدفعات المستلمة @endif
        </td>
    </tr>
    @foreach ($payments as $payment)
        @php
            $methodKey = $payment->method ? strtolower($payment->method) : null;
            $methodLabel = $methodKey && isset($paymentMethodLabels[$methodKey])
                ? $primary($paymentMethodLabels[$methodKey]['en'], $paymentMethodLabels[$methodKey]['ar'])
                : ($payment->method ?: $primary('—'));
        @endphp
        <tr style="border-top: 0.5pt solid #f1f5f9;">
            <td style="padding: 4px 10px; font-size: 8.5pt; color: #64748b;">{{ $payment->paid_at->format('Y-m-d') }}</td>
            <td style="padding: 4px 10px; font-size: 8.5pt; color: #64748b;">{{ $methodLabel }}</td>
            <td class="text-end" style="padding: 4px 10px; font-size: 8.5pt; font-weight: bold; color: #059669;">{{ \App\Support\Money::format($payment->amount) }}</td>
        </tr>
    @endforeach
</table>
