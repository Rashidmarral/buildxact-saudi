@extends('layouts.public')

@section('title', $quotation->quotation_number)

@section('content')
@if (session('status'))
    <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 print:hidden">{{ session('status') }}</div>
@endif
@if (session('error'))
    <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 print:hidden">{{ session('error') }}</div>
@endif

<div class="mb-6 flex flex-wrap items-center justify-between gap-3 print:hidden">
    <div>
        <h1 class="text-xl font-bold text-slate-900">{{ $doc['type_label'] }} {{ $quotation->quotation_number }}</h1>
        <p class="text-sm text-slate-500">{{ __('From :company', ['company' => $company->name]) }}</p>
    </div>
    <div class="flex items-center gap-2">
        <a href="{{ route('public.quotations.pdf', ['id' => $quotation->id, 'token' => $quotation->public_token]) }}" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download PDF') }}</a>
        <button onclick="window.print()" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Print') }}</button>
    </div>
</div>

<div class="bg-white rounded-xl border border-slate-100 p-8 print:border-0 print:shadow-none">
    @include('documents.print.body', ['doc' => $doc, 'company' => $company, 'template' => $template])
</div>

<div class="mt-6 print:hidden">
    @if ($quotation->status === 'accepted' || $quotation->status === 'converted')
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-6 py-5 text-center">
            <p class="font-semibold text-emerald-800">{{ __('You accepted this quotation. Thank you!') }}</p>
        </div>
    @elseif ($quotation->status === 'rejected')
        <div class="rounded-xl border border-slate-200 bg-slate-50 px-6 py-5 text-center">
            <p class="font-semibold text-slate-600">{{ __('You declined this quotation.') }}</p>
        </div>
    @elseif ($quotation->isExpired())
        <div class="rounded-xl border border-slate-200 bg-slate-50 px-6 py-5 text-center">
            <p class="font-semibold text-slate-600">{{ __('This quotation has expired.') }}</p>
        </div>
    @else
        <div class="rounded-xl border border-slate-100 bg-white p-6">
            <h2 class="font-semibold text-slate-900 mb-1">{{ __('Your decision') }}</h2>
            <p class="text-sm text-slate-500 mb-4">{{ __('Let :company know whether you would like to proceed.', ['company' => $company->name]) }}</p>

            <div id="decision-buttons" class="flex flex-wrap gap-2">
                <button type="button" id="show-accept-panel" class="rounded-lg bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Accept') }}</button>
                <form method="POST" action="{{ route('public.quotations.reject', ['id' => $quotation->id, 'token' => $quotation->public_token]) }}" onsubmit="return confirm('{{ __('Decline this quotation?') }}')">
                    @csrf
                    <button type="submit" class="rounded-lg border border-red-200 px-5 py-2.5 text-sm font-semibold text-red-600 hover:bg-red-50">{{ __('Decline') }}</button>
                </form>
            </div>

            <form id="accept-form" method="POST" action="{{ route('public.quotations.accept', ['id' => $quotation->id, 'token' => $quotation->public_token]) }}" class="hidden mt-2 rounded-lg border border-slate-200 p-4 space-y-3">
                @csrf
                <p class="text-xs text-slate-500">{{ __('Type your name and sign below to confirm your acceptance.') }}</p>

                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Your name') }}</label>
                    <input type="text" name="accepted_by_name" required class="mt-1 w-full max-w-sm rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500 mb-1">{{ __('Signature') }}</label>
                    <canvas id="signature-pad" width="400" height="140" class="w-full max-w-sm rounded-lg border border-slate-200 bg-slate-50 touch-none"></canvas>
                    <input type="hidden" name="accepted_signature" id="accepted-signature-input">
                    <button type="button" id="clear-signature" class="mt-1 text-xs font-semibold text-slate-500 hover:text-slate-700">{{ __('Clear signature') }}</button>
                    <p id="signature-required-hint" class="hidden text-xs text-red-600 mt-1">{{ __('Please sign before confirming.') }}</p>
                </div>

                <div class="flex gap-2">
                    <button type="submit" id="confirm-accept" class="rounded-lg bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Confirm acceptance') }}</button>
                    <button type="button" id="cancel-accept-panel" class="rounded-lg border border-slate-200 px-5 py-2.5 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Cancel') }}</button>
                </div>
            </form>

            @if ($company->email || $company->phone)
                <p class="mt-4 text-sm text-slate-500">
                    {{ __('Questions about this quotation?') }}
                    @if ($company->email)
                        <a href="mailto:{{ $company->email }}" class="font-semibold text-brand-700 hover:underline">{{ $company->email }}</a>
                    @endif
                    @if ($company->phone)
                        <span class="text-slate-400">·</span> <a href="tel:{{ $company->phone }}" class="font-semibold text-brand-700 hover:underline">{{ $company->phone }}</a>
                    @endif
                </p>
            @endif

            <p class="mt-4 text-sm text-slate-500">
                {{ __('Want to see all your quotes with :company in one place?', ['company' => $company->name]) }}
                <a href="{{ route('portal.login') }}" class="font-semibold text-brand-700 hover:underline">{{ __('Sign in to your account') }}</a>
            </p>
        </div>
    @endif
</div>

<script>
(function () {
    const showBtn = document.getElementById('show-accept-panel');
    const panel = document.getElementById('accept-form');
    if (! showBtn || ! panel) return;

    const decisionButtons = document.getElementById('decision-buttons');
    const cancelBtn = document.getElementById('cancel-accept-panel');
    const canvas = document.getElementById('signature-pad');
    const ctx = canvas.getContext('2d');
    const clearBtn = document.getElementById('clear-signature');
    const hint = document.getElementById('signature-required-hint');
    const signatureInput = document.getElementById('accepted-signature-input');
    let drawing = false;
    let hasDrawn = false;

    ctx.lineWidth = 2;
    ctx.lineCap = 'round';
    ctx.strokeStyle = '#1e293b';

    function pointerPos(e) {
        const rect = canvas.getBoundingClientRect();
        const scaleX = canvas.width / rect.width;
        const scaleY = canvas.height / rect.height;
        const point = e.touches ? e.touches[0] : e;
        return { x: (point.clientX - rect.left) * scaleX, y: (point.clientY - rect.top) * scaleY };
    }

    function start(e) {
        drawing = true;
        hasDrawn = true;
        const p = pointerPos(e);
        ctx.beginPath();
        ctx.moveTo(p.x, p.y);
        e.preventDefault();
    }

    function move(e) {
        if (! drawing) return;
        const p = pointerPos(e);
        ctx.lineTo(p.x, p.y);
        ctx.stroke();
        e.preventDefault();
    }

    function end() {
        drawing = false;
    }

    canvas.addEventListener('mousedown', start);
    canvas.addEventListener('mousemove', move);
    window.addEventListener('mouseup', end);
    canvas.addEventListener('touchstart', start, { passive: false });
    canvas.addEventListener('touchmove', move, { passive: false });
    canvas.addEventListener('touchend', end);

    clearBtn.addEventListener('click', function () {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        hasDrawn = false;
        hint.classList.add('hidden');
    });

    showBtn.addEventListener('click', function () {
        decisionButtons.classList.add('hidden');
        panel.classList.remove('hidden');
    });

    cancelBtn.addEventListener('click', function () {
        panel.classList.add('hidden');
        decisionButtons.classList.remove('hidden');
    });

    panel.addEventListener('submit', function (e) {
        if (! hasDrawn) {
            e.preventDefault();
            hint.classList.remove('hidden');
            return;
        }

        signatureInput.value = canvas.toDataURL('image/png');
    });
})();
</script>
@endsection
