@extends('layouts.app')

@section('title', __('Repair Jobs'))

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">{{ __('Repair Jobs') }}</h1>
        <p class="text-sm text-slate-500 mt-1">{{ __('Intake a device or vehicle, track diagnosis and repair, and check out once it\'s ready.') }}</p>
    </div>
    <button type="button" onclick="document.getElementById('new-job-modal').showModal()" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('+ New job') }}</button>
</div>

@if (session('status'))
    <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
@endif
@if ($errors->any())
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
@endif

<div class="bg-white rounded-xl border border-slate-100">
    @if ($openJobs->isEmpty())
        <p class="px-6 py-8 text-sm text-slate-500">{{ __('No open jobs right now.') }}</p>
    @else
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="px-6 py-3 font-medium">{{ __('Job') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Item') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Client') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Status') }}</th>
                    <th class="px-6 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @php($statusBadge = ['received' => 'bg-slate-100 text-slate-600', 'diagnosing' => 'bg-sky-50 text-sky-700', 'awaiting_approval' => 'bg-amber-50 text-amber-700', 'in_repair' => 'bg-violet-50 text-violet-700', 'ready' => 'bg-emerald-50 text-emerald-700'])
                @foreach ($openJobs as $job)
                    <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50">
                        <td class="px-6 py-3 font-medium text-slate-800">{{ $job->job_number }}</td>
                        <td class="px-6 py-3 text-slate-600">{{ $job->item_description }}</td>
                        <td class="px-6 py-3 text-slate-500">{{ $job->client->name ?? __('Walk-in') }}</td>
                        <td class="px-6 py-3">
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $statusBadge[$job->status] ?? 'bg-slate-100 text-slate-600' }}">{{ ucfirst(str_replace('_', ' ', $job->status)) }}</span>
                        </td>
                        <td class="px-6 py-3 text-end">
                            <a href="{{ route('app.repair-jobs.show', $job) }}" class="text-brand-700 hover:underline">{{ __('Open') }}</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

<dialog id="new-job-modal" class="rounded-2xl border border-slate-100 p-0 w-full max-w-lg backdrop:bg-slate-900/40">
    <form method="POST" action="{{ route('app.repair-jobs.store') }}" class="p-6 space-y-4">
        @csrf
        <div class="flex items-start justify-between">
            <h3 class="text-lg font-bold text-slate-900">{{ __('New job') }}</h3>
            <button type="button" onclick="document.getElementById('new-job-modal').close()" class="text-slate-400 hover:text-slate-600">✕</button>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500">{{ __('Client (optional — leave blank for a walk-in)') }}</label>
            <select name="client_id" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                <option value="">{{ __('Walk-in') }}</option>
                @foreach ($clients as $client)
                    <option value="{{ $client->id }}">{{ $client->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500">{{ __('Item') }}</label>
            <input type="text" name="item_description" placeholder="{{ __('e.g. iPhone 13 Pro, or Toyota Camry 2019') }}" required class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-xs font-medium text-slate-500">{{ __('Brand') }}</label>
                <input type="text" name="brand" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500">{{ __('Model') }}</label>
                <input type="text" name="model" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500">{{ __('Year') }}</label>
                <input type="text" name="year" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500">{{ __('Serial / plate number') }}</label>
                <input type="text" name="serial_or_plate" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
            </div>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500">{{ __('Reported issue') }}</label>
            <textarea name="issue_description" rows="3" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500"></textarea>
        </div>
        <div class="flex gap-3">
            <button type="button" onclick="document.getElementById('new-job-modal').close()" class="flex-1 rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Cancel') }}</button>
            <button type="submit" class="flex-1 rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Open job') }}</button>
        </div>
    </form>
</dialog>
@endsection
