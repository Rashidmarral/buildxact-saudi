@php
    $colors = [
        'draft' => 'bg-slate-100 text-slate-600',
        'sent' => 'bg-blue-50 text-blue-700',
        'paid' => 'bg-brand-50 text-brand-700',
        'partially_paid' => 'bg-amber-50 text-amber-700',
        'overdue' => 'bg-red-50 text-red-700',
        'cancelled' => 'bg-slate-100 text-slate-400',
    ];
    $labels = [
        'draft' => __('Draft'),
        'sent' => __('Sent'),
        'paid' => __('Paid'),
        'partially_paid' => __('Partially paid'),
        'overdue' => __('Overdue'),
        'cancelled' => __('Cancelled'),
    ];
@endphp
<span class="inline-block rounded-full px-2.5 py-1 text-xs font-medium {{ $colors[$status] ?? 'bg-slate-100 text-slate-600' }}">
    {{ $labels[$status] ?? $status }}
</span>
