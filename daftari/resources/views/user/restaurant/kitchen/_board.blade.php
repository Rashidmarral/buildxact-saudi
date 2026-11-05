@php
    $kitchenLabels = ['pending' => __('Pending'), 'preparing' => __('Preparing'), 'ready' => __('Ready'), 'served' => __('Served')];
@endphp
@if ($orders->isEmpty())
    <p class="bg-white rounded-xl border border-slate-100 px-6 py-10 text-center text-sm text-slate-400">{{ __('No active orders — the kitchen is all caught up.') }}</p>
@else
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($orders as $order)
            <div class="rounded-xl border border-slate-200 bg-white overflow-hidden">
                <div class="flex items-center justify-between px-4 py-3 border-b border-slate-100 {{ $order->status === 'ready' ? 'bg-emerald-50' : 'bg-amber-50' }}">
                    <div>
                        <p class="font-semibold text-slate-900">{{ $order->order_number }}</p>
                        <p class="text-xs text-slate-500">
                            {{ $order->order_type === 'dine_in' ? __('Dine-in') : __('Takeaway') }}
                            @if ($order->table) · {{ __('Table') }} {{ $order->table->name }} @endif
                        </p>
                    </div>
                    <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $order->status === 'ready' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                        {{ $order->status === 'ready' ? __('Ready') : __('In kitchen') }}
                    </span>
                </div>
                <ul class="divide-y divide-slate-50">
                    @foreach ($order->items as $line)
                        <li class="flex items-center justify-between gap-2 px-4 py-2.5">
                            <div>
                                <p class="text-sm font-medium text-slate-800">{{ rtrim(rtrim(number_format($line->quantity, 3), '0'), '.') }} × {{ $line->description }}</p>
                                @if ($line->notes)<p class="text-xs text-slate-400">{{ $line->notes }}</p>@endif
                            </div>
                            <select data-kitchen-status-select data-status-url="{{ route('app.restaurant.order-items.status', $line) }}" class="rounded-lg border border-slate-200 text-xs focus:border-brand-500 focus:ring-brand-500">
                                @foreach ($kitchenLabels as $value => $label)
                                    <option value="{{ $value }}" @selected($line->kitchen_status === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </div>
@endif
