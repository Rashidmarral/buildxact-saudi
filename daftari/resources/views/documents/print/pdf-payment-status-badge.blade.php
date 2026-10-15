{{--
    A small colored pill in the document header — the same visual
    language as .zatca-badge, but for payment collection state. Only
    rendered for statuses worth calling out on a printed document;
    draft/cancelled/pending_approval show nothing here. "pending" only
    ever comes from a manual payment_status_override — a plain "sent"
    invoice with nothing recorded and no override shows no badge, same
    as before this existed.
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
    <table width="100%" style="margin-top: 6px;">
        <tr>
            <td style="text-align: center;">
                <span style="display: inline-block; background-color: {{ $paymentBadge['bg'] }}; color: {{ $paymentBadge['color'] }}; font-size: 9pt; font-weight: bold; padding: 4px 14px; border-radius: 12px;">
                    {{ $primary($paymentBadge['en'], $paymentBadge['ar']) }}
                    @if ($secondary($paymentBadge['ar'])) — {{ $paymentBadge['ar'] }} @endif
                </span>
            </td>
        </tr>
    </table>
@endif
