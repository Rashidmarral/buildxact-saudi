@extends('layouts.app')

@section('title', __('Clients'))

@section('content')
<div class="flex items-center justify-between mb-6">
    <p class="text-sm text-slate-500">{{ __('Manage the clients you invoice.') }}</p>
    <div class="flex items-center gap-2">
        <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Export CSV') }}</a>
        <a href="{{ route('app.clients.import') }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Import CSV') }}</a>
        <a href="{{ route('app.clients.create') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('+ New client') }}</a>
    </div>
</div>

<div class="bg-white rounded-xl border border-slate-100">
    @if ($clients->isEmpty())
        <p class="px-6 py-8 text-sm text-slate-500">{{ __('No clients yet.') }}</p>
    @else
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="px-6 py-3 font-medium">{{ __('Code') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Name') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('VAT number') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Email') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Phone') }}</th>
                    <th class="px-6 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($clients as $client)
                    <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50">
                        <td class="px-6 py-3 text-slate-400 font-mono text-xs">{{ $client->client_code }}</td>
                        <td class="px-6 py-3 font-medium text-slate-800">
                            {{ $client->name }}
                            <span class="ms-1 text-xs text-slate-400">({{ $client->type === 'individual' ? __('Individual') : __('Company') }})</span>
                        </td>
                        <td class="px-6 py-3 text-slate-500">{{ $client->vat_number ?: '—' }}</td>
                        <td class="px-6 py-3 text-slate-500">{{ $client->email ?: '—' }}</td>
                        <td class="px-6 py-3 text-slate-500">{{ $client->phone ?: '—' }}</td>
                        <td class="px-6 py-3 text-right space-x-3 rtl:space-x-reverse">
                            <a href="{{ route('app.clients.edit', $client) }}" class="text-brand-700 hover:underline">{{ __('Edit') }}</a>
                            <form method="POST" action="{{ route('app.clients.destroy', $client) }}" class="inline" onsubmit="return confirm('{{ __('Delete this client?') }}')">
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

@include('partials.pagination', ['paginator' => $clients])
@endsection
