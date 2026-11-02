@extends('layouts.app')

@section('title', __('Suppliers'))

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <p class="text-sm text-slate-500">{{ __('Manage the suppliers you purchase from.') }}</p>
    <div class="flex items-center gap-2">
        <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Export CSV') }}</a>
        <a href="{{ route('app.suppliers.import') }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Import CSV') }}</a>
        <a href="{{ route('app.suppliers.create') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('+ New supplier') }}</a>
    </div>
</div>

<x-list-toolbar>
<div class="bg-white rounded-xl border border-slate-100">
    @if ($suppliers->isEmpty())
        <p class="px-6 py-8 text-sm text-slate-500">{{ __('No suppliers yet.') }}</p>
    @else
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="px-6 py-3 font-medium">{{ __('Code') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Name') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('VAT number') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Email') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Phone') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Next follow-up') }}</th>
                    <th class="px-6 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($suppliers as $supplier)
                    <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50">
                        <td class="px-6 py-3 text-slate-400 font-mono text-xs">{{ $supplier->supplier_code }}</td>
                        <td class="px-6 py-3 font-medium text-slate-800">{{ $supplier->display_name }}</td>
                        <td class="px-6 py-3 text-slate-500">{{ $supplier->vat_number ?: '—' }}</td>
                        <td class="px-6 py-3 text-slate-500">{{ $supplier->email ?: '—' }}</td>
                        <td class="px-6 py-3 text-slate-500">{{ $supplier->phone ?: '—' }}</td>
                        <td class="px-6 py-3">
                            @if ($supplier->next_follow_up_date)
                                <span class="{{ $supplier->next_follow_up_date->isPast() ? 'text-red-600 font-medium' : 'text-slate-500' }}">{{ $supplier->next_follow_up_date->format('Y-m-d') }}</span>
                            @else
                                <span class="text-slate-300">—</span>
                            @endif
                        </td>
                        <td class="px-6 py-3">
                            <div class="flex items-center justify-end gap-1.5">
                                <x-icon-button href="{{ route('app.suppliers.edit', $supplier) }}" icon="pencil" title="{{ __('Edit') }}" />
                                <form method="POST" action="{{ route('app.suppliers.destroy', $supplier) }}" onsubmit="return confirm('{{ __('Delete this supplier?') }}')">
                                    @csrf @method('DELETE')
                                    <x-icon-button type="submit" icon="trash" color="red" title="{{ __('Delete') }}" />
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
</x-list-toolbar>
@include('partials.pagination', ['paginator' => $suppliers])
@endsection
