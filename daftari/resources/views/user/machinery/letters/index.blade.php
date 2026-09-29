@extends('layouts.app')

@section('title', __('Letters & Agreements'))

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-xl font-bold text-slate-900">{{ __('Letters & Agreements') }}</h1>
        <p class="text-sm text-slate-500 mt-1">{{ __('Bilingual sale/purchase/rental agreements, handover letters, and any custom letter you draft.') }}</p>
    </div>
    <a href="{{ route('app.machinery.letters.create') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('+ New letter') }}</a>
</div>

<div class="bg-white rounded-xl border border-slate-100">
    @if ($letters->isEmpty())
        <p class="px-6 py-8 text-sm text-slate-500">{{ __('No letters generated yet.') }}</p>
    @else
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="px-6 py-3 font-medium">{{ __('Reference') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Title') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Linked to') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Date') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($letters as $letter)
                    <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50 cursor-pointer" onclick="window.location='{{ route('app.machinery.letters.show', $letter) }}'">
                        <td class="px-6 py-3 text-slate-500">{{ $letter->reference_number }}</td>
                        <td class="px-6 py-3 font-medium text-slate-900">{{ $letter->title }}</td>
                        <td class="px-6 py-3 text-slate-500">{{ $letter->machinery?->name ?? $letter->project?->name ?? '—' }}</td>
                        <td class="px-6 py-3">{{ $letter->letter_date->format('Y-m-d') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

<div class="mt-4">{{ $letters->links() }}</div>
@endsection
