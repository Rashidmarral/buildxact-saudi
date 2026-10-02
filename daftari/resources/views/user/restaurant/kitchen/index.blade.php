@extends('layouts.app')

@section('title', __('Kitchen Display'))

@section('content')
<input type="hidden" id="csrf-token" value="{{ csrf_token() }}">

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">{{ __('Kitchen Display') }}</h1>
        <p class="text-sm text-slate-500 mt-1">{{ __('Updates automatically every few seconds.') }}</p>
    </div>
    <a href="{{ route('app.restaurant.orders.index') }}" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Orders') }}</a>
</div>

<div id="kitchen-board">
    @include('user.restaurant.kitchen._board', ['orders' => $orders])
</div>

<script>
const kitchenBoard = document.getElementById('kitchen-board');
const feedUrl = '{{ route('app.restaurant.kitchen.feed') }}';
const csrfToken = document.getElementById('csrf-token').value;

function bindStatusSelects() {
    kitchenBoard.querySelectorAll('[data-kitchen-status-select]').forEach(select => {
        select.addEventListener('change', async () => {
            await fetch(select.dataset.statusUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: JSON.stringify({ kitchen_status: select.value }),
            });
            refreshBoard();
        });
    });
}

async function refreshBoard() {
    const res = await fetch(feedUrl);
    if (!res.ok) return;
    kitchenBoard.innerHTML = await res.text();
    bindStatusSelects();
}

bindStatusSelects();
setInterval(refreshBoard, 6000);
</script>
@endsection
