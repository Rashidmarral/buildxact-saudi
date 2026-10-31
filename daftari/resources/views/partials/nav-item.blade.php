@php
    $active = request()->routeIs($route.'*');
@endphp
<a href="{{ route($route) }}" class="group/nav relative flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-all duration-200 {{ $active ? 'bg-white/[0.08] text-white shadow-nav' : 'text-slate-400 hover:bg-white/5 hover:text-white' }}">
    @if ($active)
        <span class="absolute inset-y-1.5 start-0 w-[3px] rounded-full bg-brand-400"></span>
    @endif
    @if ($icon ?? null)
        <span class="shrink-0 transition-colors {{ $active ? 'text-brand-400' : 'text-slate-500 group-hover/nav:text-slate-300' }}">
            @include('partials.icon', ['name' => $icon, 'class' => 'h-[18px] w-[18px]'])
        </span>
    @endif
    <span class="truncate">{{ $label }}</span>
</a>
