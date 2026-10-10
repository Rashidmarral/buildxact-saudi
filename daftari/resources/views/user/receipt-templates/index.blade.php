@extends('layouts.app')

@section('title', __('Receipt Template'))

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-900">{{ __('Receipt Template') }}</h1>
    <p class="text-sm text-slate-500 mt-1">{{ __('Choose how your POS and Restaurant sale receipts look — on screen, printed, and as a downloaded PDF. This is separate from your invoice/quotation templates.') }}</p>
</div>

@if (session('status'))
    <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
@endif
@if ($errors->any())
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
@endif

<div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
    @foreach ($presets as $key => $preset)
        @php($isActive = $activeKey === $key)
        <div class="rounded-xl border {{ $isActive ? 'border-brand-400 ring-1 ring-brand-400' : 'border-slate-100' }} bg-white p-5 flex flex-col">
            <div class="flex items-start justify-between mb-3">
                <h3 class="font-semibold text-slate-900">{{ $preset['name'] }}</h3>
                @if ($isActive)
                    <span class="rounded-full bg-brand-50 px-2.5 py-0.5 text-xs font-semibold text-brand-700">✓ {{ __('Active') }}</span>
                @endif
            </div>
            <p class="text-xs text-slate-500 mb-4">
                @if ($preset['layout'] === 'receipt_detailed')
                    {{ __('Adds your logo, address, and a per-line VAT breakdown.') }}
                @else
                    {{ __('A short, thermal-style slip — company, items, totals, QR code.') }}
                @endif
            </p>
            <div class="mt-auto pt-1">
                @if ($isActive)
                    <span class="block w-full rounded-lg border border-slate-200 px-4 py-2 text-center text-sm font-semibold text-slate-400">{{ __('In use') }}</span>
                @else
                    <form method="POST" action="{{ route('app.receipt-templates.activate', $key) }}">
                        @csrf
                        <button type="submit" class="w-full rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Use this template') }}</button>
                    </form>
                @endif
            </div>
        </div>
    @endforeach
</div>
@endsection
