@extends('layouts.admin')

@section('title', __('Legal Documents'))

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">{{ __('Legal Documents') }}</h1>
        <p class="text-sm text-slate-500 mt-1">{{ __('These are drafted starting points, not reviewed legal advice. Have each one checked by a licensed Saudi legal advisor before you rely on it, then mark it reviewed and publish it.') }}</p>
    </div>
</div>

@if (session('status'))
    <div class="mb-6 rounded-lg bg-emerald-50 border border-emerald-100 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
@endif

<div class="bg-white rounded-xl border border-slate-100 overflow-hidden">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-slate-500 border-b border-slate-100">
                <th class="px-6 py-3 font-medium">{{ __('Document') }}</th>
                <th class="px-6 py-3 font-medium">{{ __('Status') }}</th>
                <th class="px-6 py-3 font-medium">{{ __('Legal review') }}</th>
                <th class="px-6 py-3 font-medium">{{ __('Last updated') }}</th>
                <th class="px-6 py-3"></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($documents as $document)
                <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50">
                    <td class="px-6 py-3 font-medium text-slate-800">{{ $document->title_en }}</td>
                    <td class="px-6 py-3">
                        @if ($document->isPublished())
                            <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700">{{ __('Published') }}</span>
                        @else
                            <span class="rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-700">{{ __('Draft') }}</span>
                        @endif
                    </td>
                    <td class="px-6 py-3">
                        @if ($document->requires_legal_review)
                            <span class="rounded-full bg-red-50 px-2.5 py-0.5 text-xs font-semibold text-red-700">{{ __('Pending review') }}</span>
                        @else
                            <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700">{{ __('Reviewed') }}</span>
                        @endif
                    </td>
                    <td class="px-6 py-3 text-slate-500">{{ $document->updated_at->format('Y-m-d') }}</td>
                    <td class="px-6 py-3 text-end space-x-3 rtl:space-x-reverse">
                        <a href="{{ route('legal', $document->slug) }}" target="_blank" class="text-slate-500 hover:underline">{{ __('Preview') }}</a>
                        <a href="{{ route('admin.legal-documents.edit', $document) }}" class="text-brand-700 hover:underline font-medium">{{ __('Edit') }}</a>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
