@php
    $colors = [
        'draft' => 'bg-slate-100 text-slate-600',
        'pending_approval' => 'bg-accent-50 text-accent-700',
        'approved' => 'bg-emerald-50 text-emerald-700',
        'rejected' => 'bg-red-50 text-red-600',
        'partially_billed' => 'bg-accent-50 text-accent-700',
        'converted' => 'bg-purple-50 text-purple-700',
        'void' => 'bg-red-50 text-red-600',
    ];
    $labels = [
        'draft' => __('Draft'),
        'pending_approval' => __('Pending approval'),
        'approved' => __('Approved'),
        'rejected' => __('Rejected'),
        'partially_billed' => __('Partially billed'),
        'converted' => __('Fully billed'),
        'void' => __('Void'),
    ];
@endphp
<span class="inline-block rounded-full px-2.5 py-1 text-xs font-medium {{ $colors[$status] ?? 'bg-slate-100 text-slate-600' }}">
    {{ $labels[$status] ?? $status }}
</span>
