@extends('layouts.app')

@section('title', $asset->name)

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

@section('content')
<div class="flex items-start justify-between mb-6">
    <div>
        <a href="{{ route('app.machinery.assets.index') }}" class="text-sm text-slate-400 hover:text-slate-600">{{ __('← Machinery & Equipment') }}</a>
        <div class="flex items-center gap-2 mt-1">
            <h1 class="text-xl font-bold text-slate-900">{{ $asset->name }}</h1>
            <span class="inline-block rounded-full {{ $statusStyles[$asset->status] ?? 'bg-slate-100 text-slate-600' }} text-xs font-medium px-2.5 py-1">{{ $statusLabels[$asset->status] ?? $asset->status }}</span>
        </div>
        <p class="text-sm text-slate-500 mt-1">{{ $asset->asset_code }} @if($asset->category) — {{ $asset->category }} @endif @if($asset->make) — {{ $asset->make }} {{ $asset->model }} @endif</p>
    </div>
    <div class="flex items-center gap-2">
        <a href="{{ route('app.machinery.assets.statement.pdf', $asset) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download statement') }}</a>
        <a href="{{ route('app.machinery.assets.edit', $asset) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Edit') }}</a>
        @if ($asset->status === 'available')
            <a href="{{ route('app.machinery.rental-contracts.create', ['machinery_asset_id' => $asset->id]) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Rent out') }}</a>
            <a href="{{ route('app.machinery.deployments.create', ['machinery_asset_id' => $asset->id]) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Deploy on project') }}</a>
        @endif
        @if ($asset->status !== 'sold')
            <button type="button" onclick="document.getElementById('sell-modal').classList.remove('hidden')" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Sell') }}</button>
        @endif
    </div>
</div>

<div class="grid sm:grid-cols-5 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-slate-100 p-5">
        <p class="text-xs text-slate-400">{{ __('Net book value') }}</p>
        <p class="text-xl font-bold text-slate-900 mt-1">{{ $asset->fixedAsset ? \App\Support\Money::format($asset->fixedAsset->netBookValue()) : '—' }}</p>
    </div>
    <div class="bg-white rounded-xl border border-slate-100 p-5">
        <p class="text-xs text-slate-400">{{ __('Total revenue') }}</p>
        <p class="text-xl font-bold text-emerald-600 mt-1">{{ \App\Support\Money::format($asset->totalRevenue()) }}</p>
    </div>
    <div class="bg-white rounded-xl border border-slate-100 p-5">
        <p class="text-xs text-slate-400">{{ __('Total running cost') }}</p>
        <p class="text-xl font-bold text-red-600 mt-1">{{ \App\Support\Money::format($asset->totalRunningCost()) }}</p>
    </div>
    <div class="bg-white rounded-xl border border-slate-100 p-5">
        <p class="text-xs text-slate-400">{{ __('Net result (est.)') }}</p>
        <p class="text-xl font-bold {{ $asset->netResult() >= 0 ? 'text-slate-900' : 'text-red-600' }} mt-1">{{ \App\Support\Money::format($asset->netResult()) }}</p>
    </div>
    @php($utilization = $asset->utilizationPercent())
    <div class="bg-white rounded-xl border border-slate-100 p-5">
        <p class="text-xs text-slate-400">{{ __('Utilization') }}</p>
        <p class="text-xl font-bold text-slate-900 mt-1">{{ $utilization !== null ? $utilization.'%' : '—' }}</p>
    </div>
</div>

<div class="grid lg:grid-cols-2 gap-6">
    <div class="bg-white rounded-xl border border-slate-100 p-6">
        <h3 class="font-semibold text-slate-900 mb-3">{{ __('Rental history') }}</h3>
        @forelse ($asset->rentalContracts as $contract)
            <a href="{{ route('app.machinery.rental-contracts.show', $contract) }}" class="flex items-center justify-between py-2.5 border-b border-slate-50 last:border-0 hover:bg-slate-50 -mx-2 px-2 rounded-lg">
                <span>
                    <span class="font-medium text-slate-800">{{ $contract->renterDisplayName() }}</span>
                    <span class="block text-xs text-slate-400">{{ $contract->start_date->format('Y-m-d') }} — {{ $contract->end_date?->format('Y-m-d') ?? __('ongoing') }}</span>
                </span>
                <span class="text-xs font-semibold {{ $contract->status === 'active' ? 'text-sky-700' : 'text-slate-500' }}">{{ ucfirst($contract->status) }}</span>
            </a>
        @empty
            <p class="text-sm text-slate-400">{{ __('No rental contracts yet.') }}</p>
        @endforelse
    </div>

    <div class="bg-white rounded-xl border border-slate-100 p-6">
        <h3 class="font-semibold text-slate-900 mb-3">{{ __('Deployment history') }}</h3>
        @forelse ($asset->deployments as $deployment)
            <div class="flex items-center justify-between py-2.5 border-b border-slate-50 last:border-0">
                <span>
                    <span class="font-medium text-slate-800">{{ $deployment->project->name }}</span>
                    <span class="block text-xs text-slate-400">{{ $deployment->start_date->format('Y-m-d') }} — {{ $deployment->end_date?->format('Y-m-d') ?? __('ongoing') }}</span>
                </span>
                <span class="text-xs font-semibold {{ $deployment->status === 'active' ? 'text-amber-700' : 'text-slate-500' }}">{{ ucfirst($deployment->status) }}</span>
            </div>
        @empty
            <p class="text-sm text-slate-400">{{ __('Not deployed on any project yet.') }}</p>
        @endforelse
    </div>

    <div class="bg-white rounded-xl border border-slate-100 p-6">
        <h3 class="font-semibold text-slate-900 mb-3">{{ __('Expenses') }}</h3>
        @forelse ($asset->expenses as $expense)
            <div class="flex items-center justify-between py-2.5 border-b border-slate-50 last:border-0">
                <span>
                    <span class="font-medium text-slate-800">{{ $expense->vendor_name ?: $expense->category?->name ?: __('Expense') }}</span>
                    <span class="block text-xs text-slate-400">{{ $expense->expense_date->format('Y-m-d') }}</span>
                </span>
                <span class="font-semibold text-red-600">{{ \App\Support\Money::format($expense->gross_amount) }}</span>
            </div>
        @empty
            <p class="text-sm text-slate-400">{{ __('No expenses tagged to this machine yet.') }}</p>
        @endforelse
    </div>

    <div class="bg-white rounded-xl border border-slate-100 p-6">
        <h3 class="font-semibold text-slate-900 mb-3">{{ __('Documents') }}</h3>
        <a href="{{ route('app.machinery.letters.create', ['machinery_asset_id' => $asset->id]) }}" class="inline-block mb-3 text-xs font-semibold text-brand-700 hover:underline">{{ __('+ Generate agreement') }}</a>
        @forelse ($asset->letters as $letter)
            <a href="{{ route('app.machinery.letters.show', $letter) }}" class="flex items-center justify-between py-2.5 border-b border-slate-50 last:border-0 hover:bg-slate-50 -mx-2 px-2 rounded-lg">
                <span>
                    <span class="font-medium text-slate-800">{{ $letter->title }}</span>
                    <span class="block text-xs text-slate-400">{{ $letter->reference_number }} — {{ $letter->letter_date->format('Y-m-d') }}</span>
                </span>
            </a>
        @empty
            <p class="text-sm text-slate-400">{{ __('No agreements generated yet.') }}</p>
        @endforelse
    </div>
</div>

<div class="bg-white rounded-xl border border-slate-100 p-6 mt-6">
    <h3 class="font-semibold text-slate-900 mb-3">{{ __('Attachments') }}</h3>
    <form method="POST" action="{{ route('app.machinery.assets.attachments.store', $asset) }}" enctype="multipart/form-data" class="mb-4">
        @csrf
        <input type="file" name="file" required class="text-sm">
        <button type="submit" class="ms-2 rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:border-slate-300">{{ __('Upload') }}</button>
    </form>
    <p class="text-xs text-slate-400 mb-3">{{ __('Photos, Istimara, insurance documents, or any other file for this machine.') }}</p>
    @forelse ($asset->attachments as $attachment)
        <div class="flex items-center justify-between py-2 border-b border-slate-50 last:border-0">
            <a href="{{ Storage::url($attachment->path) }}" target="_blank" class="text-sm text-brand-700 hover:underline">{{ $attachment->original_name }}</a>
            <span class="flex items-center gap-3">
                <span class="text-xs text-slate-400">{{ $attachment->humanSize() }}</span>
                <form method="POST" action="{{ route('app.machinery.assets.attachments.destroy', [$asset, $attachment]) }}" onsubmit="return confirm('{{ __('Remove this attachment?') }}')">
                    @csrf @method('DELETE')
                    <button type="submit" class="text-xs text-red-600 hover:underline">{{ __('Remove') }}</button>
                </form>
            </span>
        </div>
    @empty
        <p class="text-sm text-slate-400">{{ __('No attachments yet.') }}</p>
    @endforelse
</div>

<div id="sell-modal" class="hidden fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-xl max-w-md w-full p-6">
        <h3 class="font-semibold text-slate-900 mb-1">{{ __('Sell :name', ['name' => $asset->name]) }}</h3>
        <p class="text-xs text-slate-500 mb-4">{{ __('Disposes the linked Fixed Asset (posts the gain/loss to your ledger) and marks this machine sold.') }}</p>
        <form method="POST" action="{{ route('app.machinery.assets.sell', $asset) }}" class="space-y-3">
            @csrf
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Sale date') }}</label>
                <input type="date" name="sold_at" value="{{ now()->toDateString() }}" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Sale price') }}</label>
                <input type="number" step="0.01" min="0" name="sale_price" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Buyer (optional)') }}</label>
                <select name="client_id" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                    <option value="">{{ __('Not on file') }}</option>
                    @foreach (\App\Models\Client::orderBy('name')->get() as $client)
                        <option value="{{ $client->id }}">{{ $client->display_name }}</option>
                    @endforeach
                </select>
            </div>
            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" name="also_invoice" value="1">
                {{ __('Also create a draft sale invoice for this buyer') }}
            </label>
            <div class="flex items-center gap-2 pt-2">
                <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Confirm sale') }}</button>
                <button type="button" onclick="document.getElementById('sell-modal').classList.add('hidden')" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Cancel') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
