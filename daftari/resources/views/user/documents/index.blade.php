@extends('layouts.app')

@section('title', __('Document Archive'))

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-xl font-bold text-slate-900">{{ __('Document Archive') }}</h1>
        <p class="text-sm text-slate-500 mt-1">{{ __('Every supplier bill, client PO, invoice, expense receipt and other attached document in one place — for your year-end audit.') }}</p>
    </div>
    <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Export CSV') }}</a>
</div>

<form method="GET" class="bg-white rounded-xl border border-slate-100 p-4 mb-6 flex flex-wrap items-end gap-3">
    <div>
        <label class="block text-xs font-semibold uppercase text-slate-500 mb-1">{{ __('Document type') }}</label>
        <select name="type" class="rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
            <option value="">{{ __('All types') }}</option>
            @foreach ($types as $key => $class)
                <option value="{{ $key }}" @selected(request('type') === $key)>{{ __($typeLabels[$key]) }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-xs font-semibold uppercase text-slate-500 mb-1">{{ __('From') }}</label>
        <input type="date" name="from" value="{{ request('from') }}" class="rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
    </div>
    <div>
        <label class="block text-xs font-semibold uppercase text-slate-500 mb-1">{{ __('To') }}</label>
        <input type="date" name="to" value="{{ request('to') }}" class="rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
    </div>
    <div class="flex-1 min-w-[12rem]">
        <label class="block text-xs font-semibold uppercase text-slate-500 mb-1">{{ __('File name') }}</label>
        <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('Search file name') }}" class="w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
    </div>
    <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Filter') }}</button>
    @if (request()->anyFilled(['type', 'from', 'to', 'search']))
        <a href="{{ route('app.documents.index') }}" class="text-sm text-slate-400 hover:text-slate-600">{{ __('Clear') }}</a>
    @endif
</form>

<div class="bg-white rounded-xl border border-slate-100">
    @if ($attachments->isEmpty())
        <p class="px-6 py-8 text-sm text-slate-500">{{ __('No documents match your filters.') }}</p>
    @else
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="px-6 py-3 font-medium">{{ __('Date') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Document type') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Reference') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('File') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Uploaded by') }}</th>
                    <th class="px-6 py-3 font-medium"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($attachments as $attachment)
                    @php($typeKey = array_search($attachment->attachable_type, $types, true))
                    <tr class="border-b border-slate-50 last:border-0">
                        <td class="px-6 py-3 text-slate-500">{{ $attachment->created_at->format('Y-m-d') }}</td>
                        <td class="px-6 py-3">
                            <span class="inline-block rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600">{{ $typeKey ? __($typeLabels[$typeKey]) : $attachment->attachable_type }}</span>
                        </td>
                        <td class="px-6 py-3 font-medium text-slate-900">
                            @if ($attachment->reference_url)
                                <a href="{{ $attachment->reference_url }}" class="hover:underline">{{ $attachment->reference_label }}</a>
                            @else
                                {{ $attachment->reference_label }}
                            @endif
                        </td>
                        <td class="px-6 py-3">
                            <a href="{{ \Illuminate\Support\Facades\Storage::url($attachment->path) }}" target="_blank" class="text-brand-700 hover:underline">{{ $attachment->original_name }}</a>
                            <span class="block text-xs text-slate-400">{{ $attachment->humanSize() }}</span>
                        </td>
                        <td class="px-6 py-3 text-slate-500">{{ $attachment->uploader?->name ?? '—' }}</td>
                        <td class="px-6 py-3 text-right">
                            <a href="{{ \Illuminate\Support\Facades\Storage::url($attachment->path) }}" download target="_blank" class="text-xs font-semibold text-slate-600 hover:underline">{{ __('Download') }}</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

<div class="mt-4">@include('partials.pagination', ['paginator' => $attachments])</div>
@endsection
