@extends('layouts.app')

@section('title', __('Trip Log'))

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-xl font-bold text-slate-900">{{ __('Trip Log') }}</h1>
        <p class="text-sm text-slate-500 mt-1">{{ __('Who had a machine or vehicle out, where, and why.') }}</p>
    </div>
    <a href="{{ route('app.machinery.trips.create') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('+ Log a trip') }}</a>
</div>

<x-list-toolbar>
<div class="bg-white rounded-xl border border-slate-100">
    @if ($trips->isEmpty())
        <p class="px-6 py-8 text-sm text-slate-500">{{ __('No trips logged yet.') }}</p>
    @else
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="px-6 py-3 font-medium">{{ __('Date') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Machine') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Operator') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Project') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Purpose') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Route') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($trips as $trip)
                    <tr class="border-b border-slate-50 last:border-0">
                        <td class="px-6 py-3 text-slate-500">{{ $trip->trip_date->format('Y-m-d') }}</td>
                        <td class="px-6 py-3 font-medium text-slate-900">
                            <a href="{{ route('app.machinery.assets.show', $trip->machinery) }}" class="hover:underline">{{ $trip->machinery->name }}</a>
                        </td>
                        <td class="px-6 py-3">{{ $trip->operator?->full_name ?? '—' }}</td>
                        <td class="px-6 py-3">{{ $trip->project?->name ?? '—' }}</td>
                        <td class="px-6 py-3">{{ $trip->purpose }}</td>
                        <td class="px-6 py-3 text-slate-500">
                            @if ($trip->from_location || $trip->to_location)
                                {{ $trip->from_location ?? '—' }} → {{ $trip->to_location ?? '—' }}
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
</x-list-toolbar>

<div class="mt-4">@include('partials.pagination', ['paginator' => $trips])</div>
@endsection
