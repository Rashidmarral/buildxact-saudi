@extends('layouts.app')

@section('title', __('Machinery & Equipment'))

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-xl font-bold text-slate-900">{{ __('Machinery & Equipment') }}</h1>
        <p class="text-sm text-slate-500 mt-1">{{ __('Your registered machines — rent them out, deploy them on your own projects, or sell them.') }}</p>
    </div>
    <a href="{{ route('app.machinery.assets.create') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('+ New machine') }}</a>
</div>

<div class="bg-white rounded-xl border border-slate-100">
    @if ($assets->isEmpty())
        <p class="px-6 py-8 text-sm text-slate-500">{{ __('No machinery registered yet.') }}</p>
    @else
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="px-6 py-3 font-medium">{{ __('Code') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Name') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Category') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Status') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Net book value') }}</th>
                    <th class="px-6 py-3 font-medium"></th>
                </tr>
            </thead>
            <tbody>
                @php
                    $statusStyles = [
                        'available' => 'bg-emerald-50 text-emerald-700',
                        'rented_out' => 'bg-sky-50 text-sky-700',
                        'deployed' => 'bg-amber-50 text-amber-700',
                        'maintenance' => 'bg-orange-50 text-orange-700',
                        'sold' => 'bg-slate-100 text-slate-600',
                        'retired' => 'bg-slate-100 text-slate-500',
                    ];
                    $statusLabels = [
                        'available' => __('Available'),
                        'rented_out' => __('Rented out'),
                        'deployed' => __('Deployed'),
                        'maintenance' => __('Maintenance'),
                        'sold' => __('Sold'),
                        'retired' => __('Retired'),
                    ];
                @endphp
                @foreach ($assets as $asset)
                    <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50 cursor-pointer" onclick="window.location='{{ route('app.machinery.assets.show', $asset) }}'">
                        <td class="px-6 py-3 text-slate-500">{{ $asset->asset_code }}</td>
                        <td class="px-6 py-3 font-medium text-slate-900">{{ $asset->name }}</td>
                        <td class="px-6 py-3 text-slate-500">{{ $asset->category ?: '—' }}</td>
                        <td class="px-6 py-3">
                            <span class="inline-block rounded-full {{ $statusStyles[$asset->status] ?? 'bg-slate-100 text-slate-600' }} text-xs font-medium px-2.5 py-1">{{ $statusLabels[$asset->status] ?? $asset->status }}</span>
                        </td>
                        <td class="px-6 py-3 font-semibold text-slate-900">{{ $asset->fixedAsset ? \App\Support\Money::format($asset->fixedAsset->netBookValue()) : '—' }}</td>
                        <td class="px-6 py-3 text-right">
                            <span class="text-xs font-semibold text-brand-700">{{ __('View') }}</span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

<div class="mt-4">@include('partials.pagination', ['paginator' => $assets])</div>
@endsection
