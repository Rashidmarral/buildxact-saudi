@props(['icon', 'color' => 'slate', 'href' => null, 'type' => 'button', 'title' => null, 'confirm' => null, 'menu' => false])
@php
    $colors = [
        'slate' => 'border-slate-200 text-slate-600 hover:border-slate-300 hover:bg-slate-50',
        'brand' => 'border-brand-200 text-brand-700 hover:border-brand-300 hover:bg-brand-50',
        'emerald' => 'border-emerald-200 text-emerald-700 hover:border-emerald-300 hover:bg-emerald-50',
        'red' => 'border-red-200 text-red-600 hover:border-red-300 hover:bg-red-50',
        'amber' => 'border-amber-200 text-amber-700 hover:border-amber-300 hover:bg-amber-50',
    ];
    // Used as <x-icon-button menu ... /> inside <x-row-actions> — same
    // component, same color meaning, but rendered as a full-width labeled
    // menu row (icon + the existing $title text) instead of a standalone
    // square icon button, so the two can share one set of call sites.
    $menuColors = [
        'slate' => 'text-slate-700 hover:bg-slate-50',
        'brand' => 'text-brand-700 hover:bg-brand-50',
        'emerald' => 'text-emerald-700 hover:bg-emerald-50',
        'red' => 'text-red-600 hover:bg-red-50',
        'amber' => 'text-accent-700 hover:bg-accent-50',
    ];
    $classes = $menu
        ? 'flex w-full items-center gap-2.5 px-3.5 py-2 text-start text-sm font-medium transition-colors '.($menuColors[$color] ?? $menuColors['slate'])
        : 'inline-flex h-8 w-8 items-center justify-center rounded-lg border bg-white transition-colors '.($colors[$color] ?? $colors['slate']);
    $iconClasses = 'h-4 w-4 shrink-0'.($menu ? ' text-slate-400' : '');
@endphp
@if ($href)
    <a href="{{ $href }}" title="{{ $menu ? null : $title }}" {{ $attributes->merge(['class' => $classes]) }}>
        @include('partials.icon', ['name' => $icon, 'class' => $iconClasses])
        @if ($menu)<span>{{ $title }}</span>@endif
    </a>
@else
    <button type="{{ $type }}" title="{{ $menu ? null : $title }}" @if($confirm) onclick="return confirm('{{ $confirm }}')" @endif {{ $attributes->merge(['class' => $classes]) }}>
        @include('partials.icon', ['name' => $icon, 'class' => $iconClasses])
        @if ($menu)<span>{{ $title }}</span>@endif
    </button>
@endif
