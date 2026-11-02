@props(['icon', 'color' => 'slate', 'href' => null, 'type' => 'button', 'title' => null, 'confirm' => null])
@php
    $colors = [
        'slate' => 'border-slate-200 text-slate-600 hover:border-slate-300 hover:bg-slate-50',
        'brand' => 'border-brand-200 text-brand-700 hover:border-brand-300 hover:bg-brand-50',
        'emerald' => 'border-emerald-200 text-emerald-700 hover:border-emerald-300 hover:bg-emerald-50',
        'red' => 'border-red-200 text-red-600 hover:border-red-300 hover:bg-red-50',
        'amber' => 'border-amber-200 text-amber-700 hover:border-amber-300 hover:bg-amber-50',
    ];
    $classes = 'inline-flex h-8 w-8 items-center justify-center rounded-lg border bg-white transition-colors '.($colors[$color] ?? $colors['slate']);
@endphp
@if ($href)
    <a href="{{ $href }}" title="{{ $title }}" {{ $attributes->merge(['class' => $classes]) }}>
        @include('partials.icon', ['name' => $icon, 'class' => 'h-4 w-4'])
    </a>
@else
    <button type="{{ $type }}" title="{{ $title }}" @if($confirm) onclick="return confirm('{{ $confirm }}')" @endif {{ $attributes->merge(['class' => $classes]) }}>
        @include('partials.icon', ['name' => $icon, 'class' => 'h-4 w-4'])
    </button>
@endif
