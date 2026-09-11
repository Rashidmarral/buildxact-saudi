@php
    $colors = [
        'draft' => 'bg-slate-100 text-slate-600',
        'issued' => 'bg-blue-50 text-blue-700',
        'accepted' => 'bg-brand-50 text-brand-700',
        'converted' => 'bg-purple-50 text-purple-700',
        'expired' => 'bg-amber-50 text-amber-700',
        'rejected' => 'bg-red-50 text-red-700',
        'pending_approval' => 'bg-amber-50 text-amber-700',
    ];
    $labels = [
        'draft' => __('Draft'),
        'issued' => __('Issued'),
        'accepted' => __('Accepted'),
        'converted' => __('Converted'),
        'expired' => __('Expired'),
        'rejected' => __('Rejected'),
        'pending_approval' => __('Pending approval'),
    ];
@endphp
<span class="inline-block rounded-full px-2.5 py-1 text-xs font-medium {{ $colors[$status] ?? 'bg-slate-100 text-slate-600' }}">
    {{ $labels[$status] ?? $status }}
</span>
