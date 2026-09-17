@extends('layouts.app')

@section('title', $asset->name)

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-xl font-bold text-slate-900">{{ $asset->name }}</h1>
        <p class="text-sm text-slate-500 mt-1">{{ $asset->asset_code }} @if($asset->category) · {{ $asset->category }} @endif</p>
    </div>
    <div class="flex items-center gap-2">
        @if ($asset->status === 'disposed')
            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600">{{ __('Disposed') }} {{ $asset->disposed_at?->format('Y-m-d') }}</span>
        @elseif ($asset->isFullyDepreciated())
            <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-medium text-amber-700">{{ __('Fully depreciated') }}</span>
        @else
            <span class="rounded-full bg-green-100 px-3 py-1 text-xs font-medium text-green-700">{{ __('Active') }}</span>
        @endif
    </div>
</div>

<div class="grid lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
        <div class="bg-white rounded-xl border border-slate-100 p-6">
            <h2 class="text-sm font-semibold text-slate-900 mb-4">{{ __('Asset details') }}</h2>
            <dl class="grid grid-cols-2 gap-y-3 text-sm">
                <dt class="text-slate-500">{{ __('Acquisition date') }}</dt>
                <dd class="text-right font-medium text-slate-900">{{ $asset->acquisition_date->format('Y-m-d') }}</dd>

                <dt class="text-slate-500">{{ __('Acquisition cost') }}</dt>
                <dd class="text-right font-medium text-slate-900">{{ \App\Support\Money::format($asset->acquisition_cost) }}</dd>

                <dt class="text-slate-500">{{ __('Salvage value') }}</dt>
                <dd class="text-right font-medium text-slate-900">{{ \App\Support\Money::format($asset->salvage_value) }}</dd>

                <dt class="text-slate-500">{{ __('Useful life') }}</dt>
                <dd class="text-right font-medium text-slate-900">{{ __(':years years', ['years' => $asset->useful_life_years]) }}</dd>

                <dt class="text-slate-500">{{ __('Monthly depreciation') }}</dt>
                <dd class="text-right font-medium text-slate-900">{{ \App\Support\Money::format($asset->monthlyDepreciation()) }}</dd>

                <dt class="text-slate-500">{{ __('Accumulated depreciation') }}</dt>
                <dd class="text-right font-medium text-slate-900">{{ \App\Support\Money::format($asset->accumulated_depreciation) }}</dd>

                <dt class="font-semibold text-slate-900 pt-3 border-t border-slate-100 mt-1">{{ __('Net book value') }}</dt>
                <dd class="text-right font-bold text-brand-700 pt-3 border-t border-slate-100 mt-1">{{ \App\Support\Money::format($asset->netBookValue()) }}</dd>
            </dl>
            @if ($asset->notes)
                <div class="mt-5 pt-5 border-t border-slate-100">
                    <p class="text-xs font-medium text-slate-500 mb-1">{{ __('Notes') }}</p>
                    <p class="text-sm text-slate-700 whitespace-pre-line">{{ $asset->notes }}</p>
                </div>
            @endif
        </div>

        <div class="bg-white rounded-xl border border-slate-100">
            <h2 class="text-sm font-semibold text-slate-900 px-6 pt-6 pb-2">{{ __('Depreciation history') }}</h2>
            @if ($depreciationEntries->isEmpty())
                <p class="px-6 py-6 text-sm text-slate-500">{{ __('No depreciation has been posted yet.') }}</p>
            @else
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-slate-500 border-b border-slate-100">
                            <th class="px-6 py-3 font-medium">{{ __('Date') }}</th>
                            <th class="px-6 py-3 font-medium">{{ __('Description') }}</th>
                            <th class="px-6 py-3 font-medium text-right">{{ __('Amount') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($depreciationEntries as $entry)
                            <tr class="border-b border-slate-50 last:border-0">
                                <td class="px-6 py-3 text-slate-500">{{ $entry->entry_date->format('Y-m-d') }}</td>
                                <td class="px-6 py-3">
                                    <a href="{{ route('app.journals.show', $entry) }}" class="text-brand-700 hover:underline">{{ $entry->description }}</a>
                                </td>
                                <td class="px-6 py-3 text-right font-medium text-slate-900">{{ \App\Support\Money::format($entry->lines->sum('debit')) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    <div class="space-y-6">
        @if ($asset->status === 'active')
            <div class="bg-white rounded-xl border border-slate-100 p-6">
                <h2 class="text-sm font-semibold text-slate-900 mb-3">{{ __('Dispose asset') }}</h2>
                <form method="POST" action="{{ route('app.fixed-assets.dispose', $asset) }}" onsubmit="return confirm('{{ __('Dispose this asset? This cannot be undone.') }}')" class="space-y-3">
                    @csrf
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Disposal date') }}</label>
                        <input type="date" name="disposed_at" value="{{ now()->toDateString() }}" required class="w-full rounded-lg border border-slate-200 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Disposal proceeds') }}</label>
                        <input type="number" step="0.01" min="0" name="disposal_proceeds" value="0" class="w-full rounded-lg border border-slate-200 text-sm">
                        <p class="text-xs text-slate-400 mt-1">{{ __('Amount received from sale/disposal, if any. Gain or loss versus net book value posts automatically.') }}</p>
                    </div>
                    <button type="submit" class="w-full rounded-lg border border-red-200 px-4 py-2 text-sm font-semibold text-red-600 hover:bg-red-50">{{ __('Dispose asset') }}</button>
                </form>
            </div>
        @endif
    </div>
</div>

<a href="{{ route('app.fixed-assets.index') }}" class="mt-6 inline-block text-sm font-semibold text-brand-700 hover:underline">{{ __('Back to fixed assets') }}</a>
@endsection
