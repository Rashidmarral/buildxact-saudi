{{--
    A small colored pill in the document header — the same visual
    language as .zatca-badge, but for payment collection state. Only
    rendered for statuses worth calling out on a printed document;
    draft/sent/cancelled/pending_approval show nothing here.
--}}
@php
    $paymentBadge = match ($doc['payment_status'] ?? null) {
        'pending' => ['bg' => '#e0f2fe', 'color' => '#0369a1', 'en' => 'Pending', 'ar' => 'قيد الانتظار'],
        'partially_paid' => ['bg' => '#fef3c7', 'color' => '#b45309', 'en' => 'Partially Paid', 'ar' => 'مدفوعة جزئيًا'],
        'paid' => ['bg' => '#d1fae5', 'color' => '#047857', 'en' => 'Paid', 'ar' => 'مدفوعة بالكامل'],
        'overdue' => ['bg' => '#fee2e2', 'color' => '#b91c1c', 'en' => 'Overdue', 'ar' => 'متأخرة السداد'],
        default => null,
    };
@endphp
@if ($paymentBadge)
    <table width="100%" style="margin-top: 2px;">
        <tr>
            <td style="text-align: center;">
                <span style="display: inline-block; background-color: {{ $paymentBadge['bg'] }}; color: {{ $paymentBadge['color'] }}; font-size: 8pt; font-weight: bold; padding: 2px 12px; border-radius: 10px;">
                    {{ $primary($paymentBadge['en'], $paymentBadge['ar']) }}
                    @if ($secondary($paymentBadge['ar'])) — {{ $paymentBadge['ar'] }} @endif
                </span>
            </td>
        </tr>
    </table>
@endif
