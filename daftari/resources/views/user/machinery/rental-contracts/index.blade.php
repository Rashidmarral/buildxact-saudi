@extends('layouts.app')

@section('title', __('Rental Contracts'))

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-xl font-bold text-slate-900">{{ __('Rental Contracts') }}</h1>
        <p class="text-sm text-slate-500 mt-1">{{ __('Machinery rented out to clients.') }}</p>
    </div>
    <a href="{{ route('app.machinery.rental-contracts.create') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('+ New rental') }}</a>
</div>

<div class="bg-white rounded-xl border border-slate-100">
    @if ($contracts->isEmpty())
        <p class="px-6 py-8 text-sm text-slate-500">{{ __('No rental contracts yet.') }}</p>
    @else
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="px-6 py-3 font-medium">{{ __('Contract') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Machine') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Renter') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Period') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Rate') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Status') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($contracts as $contract)
                    <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50 cursor-pointer" onclick="window.location='{{ route('app.machinery.rental-contracts.show', $contract) }}'">
                        <td class="px-6 py-3 text-slate-500">{{ $contract->contract_number }}</td>
                        <td class="px-6 py-3 font-medium text-slate-900">{{ $contract->machinery->name }}</td>
                        <td class="px-6 py-3">{{ $contract->renterDisplayName() }}</td>
                        <td class="px-6 py-3 text-slate-500">{{ $contract->start_date->format('Y-m-d') }} — {{ $contract->end_date?->format('Y-m-d') ?? __('ongoing') }}</td>
                        <td class="px-6 py-3">{{ \App\Support\Money::format($contract->rate) }} / {{ __(ucfirst($contract->rate_type)) }}</td>
                        <td class="px-6 py-3">
                            <span class="inline-block rounded-full {{ $contract->status === 'active' ? 'bg-sky-50 text-sky-700' : 'bg-slate-100 text-slate-600' }} text-xs font-medium px-2.5 py-1">{{ ucfirst($contract->status) }}</span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

<div class="mt-4">{{ $contracts->links() }}</div>
@endsection
