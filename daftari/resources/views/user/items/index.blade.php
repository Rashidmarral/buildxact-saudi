@extends('layouts.app')

@section('title', __('Items'))

@section('content')
<div class="flex items-center justify-between mb-6">
    <p class="text-sm text-slate-500">{{ __('Your reusable catalog of products and services.') }}</p>
    <a href="{{ route('app.items.create') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('+ New item') }}</a>
</div>

<div class="bg-white rounded-xl border border-slate-100">
    @if ($items->isEmpty())
        <p class="px-6 py-8 text-sm text-slate-500">{{ __('No items yet.') }}</p>
    @else
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="px-6 py-3 font-medium">{{ __('Name') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Unit price') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('VAT rate') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Status') }}</th>
                    <th class="px-6 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($items as $item)
                    <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50">
                        <td class="px-6 py-3 font-medium text-slate-800">{{ $item->name }}</td>
                        <td class="px-6 py-3 text-slate-500">SAR {{ number_format($item->unit_price, 2) }}</td>
                        <td class="px-6 py-3 text-slate-500">{{ rtrim(rtrim(number_format($item->vat_rate, 2), '0'), '.') }}%</td>
                        <td class="px-6 py-3">
                            @if ($item->is_active)
                                <span class="text-brand-700">{{ __('Active') }}</span>
                            @else
                                <span class="text-slate-400">{{ __('Inactive') }}</span>
                            @endif
                        </td>
                        <td class="px-6 py-3 text-right space-x-3 rtl:space-x-reverse">
                            <a href="{{ route('app.items.edit', $item) }}" class="text-brand-700 hover:underline">{{ __('Edit') }}</a>
                            <form method="POST" action="{{ route('app.items.destroy', $item) }}" class="inline" onsubmit="return confirm('{{ __('Delete this item?') }}')">
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

<div class="mt-4">{{ $items->links() }}</div>
@endsection
