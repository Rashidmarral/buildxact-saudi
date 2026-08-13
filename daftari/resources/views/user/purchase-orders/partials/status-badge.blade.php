@php
    $colors = [
        'draft' => 'bg-slate-100 text-slate-600',
        'approved' => 'bg-brand-50 text-brand-700',
        'converted' => 'bg-purple-50 text-purple-700',
        'void' => 'bg-red-50 text-red-600',
    ];
    $labels = [
        'draft' => __('Draft'),
        'approved' => __('Approved'),
        'converted' => __('Converted'),
        'void' => __('Void'),
    ];
@endphp
<span class="inline-block rounded-full px-2.5 py-1 text-xs font-medium {{ $colors[$status] ?? 'bg-slate-100 text-slate-600' }}">
    {{ $labels[$status] ?? $status }}
</span>
