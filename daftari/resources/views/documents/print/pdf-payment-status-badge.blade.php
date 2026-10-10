{{--
    A small colored pill next to the totals box — the same visual
    language as .zatca-badge, but for payment collection state. Only
    rendered for the three statuses worth calling out on a printed
    document; draft/sent/cancelled show nothing here (the Paid/Balance
    due rows above already read as "nothing collected yet" on their own).
--}}
@php
    $paymentBadge = match ($doc['payment_status'] ?? null) {
        'partially_paid' => ['bg' => '#fef3c7', 'color' => '#b45309', 'en' => 'Partially Paid', 'ar' => 'مدفوعة جزئيًا'],
        'paid' => ['bg' => '#d1fae5', 'color' => '#047857', 'en' => 'Paid', 'ar' => 'مدفوعة بالكامل'],
        'overdue' => ['bg' => '#fee2e2', 'color' => '#b91c1c', 'en' => 'Overdue', 'ar' => 'متأخرة السداد'],
        default => null,
    };
@endphp
@if ($paymentBadge)
    <table style="margin-top: 6px;">
        <tr>
            <td style="width: 55%;"></td>
            <td style="width: 45%; text-align: center;">
                <span style="display: inline-block; background-color: {{ $paymentBadge['bg'] }}; color: {{ $paymentBadge['color'] }}; font-size: 9pt; font-weight: bold; padding: 4px 14px; border-radius: 12px;">
                    {{ $primary($paymentBadge['en'], $paymentBadge['ar']) }}
                    @if ($secondary($paymentBadge['ar'])) — {{ $paymentBadge['ar'] }} @endif
                </span>
            </td>
        </tr>
    </table>
@endif
