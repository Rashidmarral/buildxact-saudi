@extends('layouts.app')

@section('title', __('Salespersons'))

@section('content')
<div class="flex items-center justify-between mb-6">
    <p class="text-sm text-slate-500">{{ __('Manage salespersons and see what they\'ve sold.') }}</p>
    <a href="{{ route('app.salespersons.create') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('+ New salesperson') }}</a>
</div>

<x-list-toolbar>
<div class="bg-white rounded-xl border border-slate-100">
    @if ($salespersons->isEmpty())
        <p class="px-6 py-8 text-sm text-slate-500">{{ __('No salespersons yet.') }}</p>
    @else
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="px-6 py-3 font-medium">{{ __('Name') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Email') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Phone') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Commission') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Invoices') }}</th>
                    <th class="px-6 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($salespersons as $salesperson)
                    <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50">
                        <td class="px-6 py-3 font-medium text-slate-800">
                            {{ $salesperson->name }}
                            @unless ($salesperson->is_active)
                                <span class="ms-1 text-xs text-slate-400">({{ __('Inactive') }})</span>
                            @endunless
                        </td>
                        <td class="px-6 py-3 text-slate-500">{{ $salesperson->email ?: '—' }}</td>
                        <td class="px-6 py-3 text-slate-500">{{ $salesperson->phone ?: '—' }}</td>
                        <td class="px-6 py-3 text-slate-500">{{ $salesperson->commission_rate !== null ? $salesperson->commission_rate.'%' : '—' }}</td>
                        <td class="px-6 py-3 text-slate-500">{{ $salesperson->invoices_count }}</td>
                        <td class="px-6 py-3 text-right">
                            <div class="flex items-center justify-end gap-1.5">
                                <x-icon-button href="{{ route('app.salespersons.edit', $salesperson) }}" icon="pencil" title="{{ __('Edit') }}" />
                                <form method="POST" action="{{ route('app.salespersons.destroy', $salesperson) }}" onsubmit="return confirm('{{ __('Delete this salesperson?') }}')">
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
@endsection
