@php
    $compact = $compact ?? false;
    $accent = $tpl->accent_color;
    $layout = $tpl->layout;
    $pad = $compact ? 'p-3' : 'p-5';
    $textSize = $compact ? 'text-[8px]' : 'text-xs';
@endphp
<div class="rounded-lg border border-slate-100 bg-white {{ $pad }} {{ $textSize }} leading-relaxed" style="font-family: inherit;">
    @if ($layout === 'bordered')
        <div class="h-1.5 rounded-full mb-3" style="background-color: {{ $accent }}"></div>
    @endif

    <div class="flex items-start justify-between {{ $layout === 'bordered' ? 'border-s-2 ps-3' : '' }}" style="{{ $layout === 'bordered' ? 'border-color: '.$accent : '' }}">
        <div>
            <p class="font-bold" style="color: {{ $layout === 'minimal' ? $accent : '#0f172a' }}">{{ __('Tax Invoice') }}</p>
            <p class="text-slate-400 mt-1">{{ __('INV-00001') }}</p>
        </div>
        <div class="text-end text-slate-400">
            <p>{{ now()->format('Y-m-d') }}</p>
        </div>
    </div>

    <div class="mt-3 grid grid-cols-2 gap-3">
        <div class="rounded {{ $layout === 'bordered' ? 'border-s-2 ps-2' : '' }}" style="{{ $layout === 'bordered' ? 'border-color: '.$accent : '' }}">
            <p class="text-slate-400">{{ __('Seller') }}</p>
            <p class="font-semibold text-slate-700">{{ auth()->user()->company->name ?? 'Company' }}</p>
        </div>
        <div class="text-end">
            <p class="text-slate-400">{{ __('Customer') }}</p>
            <p class="font-semibold text-slate-700">{{ __('Client name') }}</p>
        </div>
    </div>

    <div class="mt-3 rounded" style="{{ $layout === 'boxed' ? 'background-color:'.$accent.'1a' : '' }}">
        <div class="flex justify-between px-2 py-1 font-semibold text-white rounded-t" style="background-color: {{ $accent }}">
            <span>{{ __('Item') }}</span><span>{{ __('Total') }}</span>
        </div>
        <div class="flex justify-between px-2 py-1 text-slate-600">
            <span>{{ __('Professional services') }}</span><span>1,725.00</span>
        </div>
    </div>

    @if ($layout === 'boxed')
        <div class="mt-3 rounded-lg p-2 text-white flex justify-between font-semibold" style="background-color: {{ $accent }}">
            <span>{{ __('Total') }}</span><span>1,983.75</span>
        </div>
    @else
        <div class="mt-3 flex justify-between font-semibold border-t pt-1.5" style="color: {{ $accent }}; border-color: {{ $accent }}">
            <span>{{ __('Total') }}</span><span>1,983.75</span>
        </div>
    @endif

    @if (! $compact && $tpl->notesFor(app()->getLocale()) ?? null)
        <p class="mt-3 text-slate-400 border-t border-slate-100 pt-2">{{ $tpl->notesFor(app()->getLocale()) }}</p>
    @endif
</div>
