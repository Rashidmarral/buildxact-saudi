@extends('layouts.app')

@section('title', __('Fixed Assets'))

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-xl font-bold text-slate-900">{{ __('Fixed Assets') }}</h1>
        <p class="text-sm text-slate-500 mt-1">{{ __('Register and track fixed assets, their book value, and monthly depreciation.') }}</p>
    </div>
    <div class="flex gap-2">
        <form method="POST" action="{{ route('app.fixed-assets.run-depreciation') }}" onsubmit="return confirm('{{ __('Post this month\'s depreciation for all active assets?') }}')">
            @csrf
            <button type="submit" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Run depreciation') }}</button>
        </form>
        <a href="{{ route('app.fixed-assets.create') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('New asset') }}</a>
    </div>
</div>

<div class="bg-white rounded-xl border border-slate-100">
    @if ($assets->isEmpty())
        <p class="px-6 py-8 text-sm text-slate-500">{{ __('No fixed assets registered yet.') }}</p>
    @else
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="px-6 py-3 font-medium">{{ __('Code') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Name') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Category') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Acquisition cost') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Accum. depreciation') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Net book value') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Status') }}</th>
                    <th class="px-6 py-3 font-medium"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($assets as $asset)
                    <tr class="border-b border-slate-50 last:border-0">
                        <td class="px-6 py-3 text-slate-500">{{ $asset->asset_code }}</td>
                        <td class="px-6 py-3 font-medium text-slate-900">{{ $asset->name }}</td>
                        <td class="px-6 py-3 text-slate-500">{{ $asset->category ?: '—' }}</td>
                        <td class="px-6 py-3">{{ \App\Support\Money::format($asset->acquisition_cost) }}</td>
                        <td class="px-6 py-3">{{ \App\Support\Money::format($asset->accumulated_depreciation) }}</td>
                        <td class="px-6 py-3 font-semibold text-slate-900">{{ \App\Support\Money::format($asset->netBookValue()) }}</td>
                        <td class="px-6 py-3">
                            @if ($asset->status === 'disposed')
                                <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600">{{ __('Disposed') }}</span>
                            @elseif ($asset->isFullyDepreciated())
                                <span class="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-700">{{ __('Fully depreciated') }}</span>
                            @else
                                <span class="rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-700">{{ __('Active') }}</span>
                            @endif
                        </td>
                        <td class="px-6 py-3 text-right">
                            <a href="{{ route('app.fixed-assets.show', $asset) }}" class="text-xs font-semibold text-brand-700 hover:underline">{{ __('View') }}</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

<div class="mt-4">{{ $assets->links() }}</div>
@endsection
