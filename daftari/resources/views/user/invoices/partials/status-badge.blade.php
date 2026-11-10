@php
    $colors = [
        'draft' => 'bg-slate-100 text-slate-600',
        'sent' => 'bg-blue-50 text-blue-700',
        'pending' => 'bg-sky-50 text-sky-700',
        'paid' => 'bg-emerald-50 text-emerald-700',
        'partially_paid' => 'bg-accent-50 text-accent-700',
        'overdue' => 'bg-red-50 text-red-700',
        'cancelled' => 'bg-slate-100 text-slate-400',
        'pending_approval' => 'bg-accent-50 text-accent-700',
    ];
    $labels = [
        'draft' => __('Draft'),
        'sent' => __('Sent'),
        'pending' => __('Pending'),
        'paid' => __('Paid'),
        'partially_paid' => __('Partially paid'),
        'overdue' => __('Overdue'),
        'cancelled' => __('Cancelled'),
        'pending_approval' => __('Pending approval'),
    ];
@endphp
<span class="inline-block rounded-full px-2.5 py-1 text-xs font-medium {{ $colors[$status] ?? 'bg-slate-100 text-slate-600' }}">
    {{ $labels[$status] ?? $status }}
</span>
