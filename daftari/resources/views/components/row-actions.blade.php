@props(['label' => null])
@if (! $slot->isEmpty())
{{--
    A single "Actions" dropdown button for a table row, replacing a row of
    standalone icon buttons with one compact, premium-looking control (the
    pattern Hamly ERP uses on its list pages). Slot content is one or more
    <x-icon-button menu ... /> (or a <form> wrapping one) — the same
    component used for the old inline icon buttons, just rendered as a
    full-width labeled menu row via its `menu` prop so both styles share
    one set of call sites.
--}}
{{--
    The menu panel is positioned via fixed coordinates computed from the
    trigger button's bounding rect (rather than `absolute` + `top-full`)
    because every list page wraps its table in an `overflow-x-auto`
    container (see list-toolbar.blade.php) for mobile-safety — per the CSS
    overflow spec, setting overflow-x to a non-'visible' value forces the
    paired overflow-y to 'auto' too, which silently clips an absolutely
    positioned dropdown that extends past the table's bottom edge. Fixed
    positioning escapes that ancestor clipping entirely.
--}}
<div class="relative inline-block text-start" x-data="{ open: false }" @click.outside="open = false" @scroll.window="open = false" @resize.window="open = false">
    <button
        type="button"
        x-ref="trigger"
        @click="
            open = !open;
            if (open) {
                const r = $refs.trigger.getBoundingClientRect();
                const w = $refs.menu.offsetWidth || 224;
                let left = r.right - w;
                if (left < 8) left = 8;
                $refs.menu.style.top = (r.bottom + 6) + 'px';
                $refs.menu.style.left = left + 'px';
            }
        "
        class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 shadow-soft transition-colors hover:border-brand-300 hover:text-brand-700"
    >
        {{ $label ?? __('Actions') }}
        <span class="transition-transform duration-150" :class="open ? 'rotate-180' : ''">
            @include('partials.icon', ['name' => 'chevron-down', 'class' => 'h-3.5 w-3.5'])
        </span>
    </button>
    <div
        x-ref="menu"
        x-show="open"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        @click="open = false"
        class="fixed z-40 w-56 rounded-xl border border-slate-100 bg-white py-1.5 shadow-card-hover"
        style="display: none;"
    >
        {{ $slot }}
    </div>
</div>
@endif
