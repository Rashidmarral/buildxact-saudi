@extends('layouts.app')

@section('title', __('Warehouses'))

@section('content')
<div class="flex flex-wrap items-center gap-2 mb-6 text-sm">
    <a href="{{ route('app.warehouses.index') }}" class="rounded-lg px-3 py-1.5 bg-slate-900 text-white">{{ __('Warehouses') }}</a>
    <a href="{{ route('app.stock-adjustments.index') }}" class="rounded-lg px-3 py-1.5 text-slate-600 hover:bg-slate-100">{{ __('Stock adjustments') }}</a>
    <a href="{{ route('app.inventory.stock') }}" class="rounded-lg px-3 py-1.5 text-slate-600 hover:bg-slate-100">{{ __('Stock levels') }}</a>
</div>

<div class="flex items-center justify-between mb-6">
    <p class="text-sm text-slate-500">{{ __('Locations where you store inventory.') }}</p>
    <a href="{{ route('app.warehouses.create') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('+ New warehouse') }}</a>
</div>

<div class="bg-white rounded-xl border border-slate-100">
    @if ($warehouses->isEmpty())
        <p class="px-6 py-8 text-sm text-slate-500">{{ __('No warehouses yet.') }}</p>
    @else
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="px-6 py-3 font-medium">{{ __('Name') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Address') }}</th>
                    <th class="px-6 py-3 font-medium"></th>
                    <th class="px-6 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($warehouses as $warehouse)
                    <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50">
                        <td class="px-6 py-3 font-medium text-slate-800">{{ $warehouse->name }}</td>
                        <td class="px-6 py-3 text-slate-500">{{ $warehouse->address ?: '—' }}</td>
                        <td class="px-6 py-3">
                            @if ($warehouse->is_default)
                                <span class="inline-block rounded-full bg-brand-50 text-brand-700 text-xs font-medium px-2.5 py-1">{{ __('Default') }}</span>
                            @else
                                <form method="POST" action="{{ route('app.warehouses.make-default', $warehouse) }}">
                                    @csrf
                                    <button type="submit" class="text-xs font-semibold text-slate-500 hover:text-brand-700">{{ __('Set as default') }}</button>
                                </form>
                            @endif
                        </td>
                        <td class="px-6 py-3 text-right space-x-3 rtl:space-x-reverse">
                            <a href="{{ route('app.warehouses.edit', $warehouse) }}" class="text-brand-700 hover:underline">{{ __('Edit') }}</a>
                            <form method="POST" action="{{ route('app.warehouses.destroy', $warehouse) }}" class="inline" onsubmit="return confirm('{{ __('Delete this warehouse?') }}')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-red-600 hover:underline">{{ __('Delete') }}</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
@endsection
