@php
    $colors = [
        'draft' => 'bg-slate-100 text-slate-600',
        'pending_approval' => 'bg-accent-50 text-accent-700',
        'posted' => 'bg-emerald-50 text-emerald-700',
        'void' => 'bg-red-50 text-red-600',
    ];
    $labels = [
        'draft' => __('Draft'),
        'pending_approval' => __('Pending approval'),
        'posted' => __('Posted'),
        'void' => __('Void'),
    ];
@endphp
<span class="inline-block rounded-full px-2.5 py-1 text-xs font-medium {{ $colors[$status] ?? 'bg-slate-100 text-slate-600' }}">
    {{ $labels[$status] ?? $status }}
</span>
