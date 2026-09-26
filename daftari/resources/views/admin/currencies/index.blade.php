@extends('layouts.admin')

@section('title', __('Currencies'))

@section('content')
<div class="flex items-center justify-between mb-6">
    <p class="text-sm text-slate-500">{{ __('Currencies companies can pick for their own invoices, bills, and reports.') }}</p>
    <a href="{{ route('admin.currencies.create') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('+ New currency') }}</a>
</div>

<div class="bg-white rounded-xl border border-slate-100">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-slate-500 border-b border-slate-100">
                <th class="px-6 py-3 font-medium">{{ __('Code') }}</th>
                <th class="px-6 py-3 font-medium">{{ __('Name') }}</th>
                <th class="px-6 py-3 font-medium">{{ __('Symbol') }}</th>
                <th class="px-6 py-3 font-medium">{{ __('Format') }}</th>
                <th class="px-6 py-3 font-medium">{{ __('Status') }}</th>
                <th class="px-6 py-3"></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($currencies as $currency)
                <tr class="border-b border-slate-50 last:border-0">
                    <td class="px-6 py-3 font-mono font-semibold text-slate-800">{{ $currency->code }}</td>
                    <td class="px-6 py-3 text-slate-700">{{ $currency->name }}</td>
                    <td class="px-6 py-3">{{ $currency->symbol }}</td>
                    <td class="px-6 py-3 text-slate-500">{{ \App\Support\Money::format(1234.5, $currency->code) }}</td>
                    <td class="px-6 py-3">
                        @if ($currency->is_default)
                            <span class="rounded-full bg-brand-50 px-2.5 py-1 text-xs font-semibold text-brand-700">{{ __('Default') }}</span>
                        @elseif ($currency->is_active)
                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">{{ __('Active') }}</span>
                        @else
                            <span class="rounded-full bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-400">{{ __('Inactive') }}</span>
                        @endif
                    </td>
                    <td class="px-6 py-3 text-right space-x-3 rtl:space-x-reverse">
                        <a href="{{ route('admin.currencies.edit', $currency) }}" class="text-brand-700 hover:underline">{{ __('Edit') }}</a>
                        <form method="POST" action="{{ route('admin.currencies.destroy', $currency) }}" class="inline" onsubmit="return confirm('{{ __('Delete this currency?') }}')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-red-600 hover:underline">{{ __('Delete') }}</button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
