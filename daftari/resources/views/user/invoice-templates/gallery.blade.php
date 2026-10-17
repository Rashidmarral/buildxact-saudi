@extends('layouts.app')

@section('title', __('Invoice Templates'))

@section('content')
<div class="mb-6 flex flex-wrap items-start justify-between gap-3">
    <div>
        <h2 class="text-lg font-semibold text-slate-900">{{ __('Invoice Templates') }}</h2>
        <p class="text-sm text-slate-500 mt-1">{{ __('Pick a layout below — it\'s applied to every invoice, quotation, bill, purchase order and voucher, on screen and in every downloaded or emailed PDF.') }}</p>
    </div>
    <a href="{{ route('app.invoice-templates.index') }}" class="shrink-0 rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">
        {{ __('Advanced customization') }}
    </a>
</div>

@if (session('status'))
    <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
@endif
@if ($errors->any())
    <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
@endif

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
    @foreach (array_merge(['default' => ['name' => __('Default'), 'name_ar' => null]], $presets) as $key => $preset)
        @php $isActive = $activePresetKey === $key || ($activePresetKey === null && $key === 'default'); @endphp
        <div class="bg-white rounded-xl border {{ $isActive ? 'border-brand-400 ring-1 ring-brand-400' : 'border-slate-100' }} overflow-hidden flex flex-col">
            <div class="relative bg-slate-50 border-b border-slate-100" style="aspect-ratio: 3/4;">
                <iframe
                    src="{{ route('app.invoice-templates.gallery.preview', $key) }}"
                    loading="lazy"
                    title="{{ $preset['name'] }}"
                    class="absolute top-0 left-0 pointer-events-none"
                    style="width: 400%; height: 400%; transform: scale(0.25); transform-origin: top left; border: 0;"
                ></iframe>
                @if ($isActive)
                    <span class="absolute top-2 end-2 rounded-full bg-brand-600 px-2.5 py-1 text-[11px] font-semibold text-white shadow">{{ __('Active') }}</span>
                @endif
            </div>
            <div class="p-4 flex flex-col items-center gap-3 flex-1">
                <p class="font-semibold text-slate-800 text-sm">{{ $preset['name'] }}</p>
                @if ($isActive)
                    <span class="mt-auto w-full text-center rounded-lg bg-emerald-50 text-emerald-700 px-4 py-2 text-sm font-semibold">✓ {{ __('Active invoice layout') }}</span>
                @else
                    <form method="POST" action="{{ route('app.invoice-templates.gallery.activate', $key) }}" class="mt-auto w-full">
                        @csrf
                        <button type="submit" class="w-full rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                            {{ __('Activate invoice layout') }}
                        </button>
                    </form>
                @endif
            </div>
        </div>
    @endforeach
</div>
@endsection
