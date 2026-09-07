@extends('layouts.admin')

@section('title', __('Compliance Readiness'))

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-900">{{ __('Compliance Readiness') }}</h1>
    <p class="text-sm text-slate-500 mt-1">{{ __('A plain checklist for whether this platform is set up to sell legally in Saudi Arabia. Nothing here is a government certification or legal sign-off — each item is a real question for your own business, tax, or legal advisor.') }}</p>
</div>

@if (session('status'))
    <div class="mb-6 rounded-lg bg-emerald-50 border border-emerald-100 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
@endif

<div class="space-y-6 max-w-3xl">
    {{-- Legal identity --}}
    <div class="bg-white rounded-xl border border-slate-100 p-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="font-semibold text-slate-900">{{ __('Legal identity') }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ __('Your registered company name, VAT number, CR number, and address, shown on SaaS subscription receipts.') }}</p>
            </div>
            @if ($identityConfigured)
                <span class="shrink-0 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">{{ __('Configured') }}</span>
            @else
                <span class="shrink-0 rounded-full bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-700">{{ __('Incomplete') }}</span>
            @endif
        </div>
        <dl class="mt-4 grid sm:grid-cols-2 gap-3 text-sm">
            <div><dt class="text-slate-400">{{ __('Name') }}</dt><dd class="font-medium text-slate-800">{{ $branding['name'] ?: '—' }}</dd></div>
            <div><dt class="text-slate-400">{{ __('VAT number') }}</dt><dd class="font-medium text-slate-800">{{ $branding['vat_number'] ?: '—' }}</dd></div>
            <div><dt class="text-slate-400">{{ __('CR number') }}</dt><dd class="font-medium text-slate-800">{{ $branding['cr_number'] ?: '—' }}</dd></div>
            <div><dt class="text-slate-400">{{ __('Address') }}</dt><dd class="font-medium text-slate-800">{{ $branding['address'] ?: '—' }}</dd></div>
        </dl>
        <a href="{{ route('admin.settings.edit') }}" class="mt-4 inline-block text-sm font-semibold text-brand-700 hover:underline">{{ __('Edit in Platform Settings') }} &rarr;</a>
    </div>

    {{-- CR activity suitability --}}
    <div class="bg-white rounded-xl border border-slate-100 p-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="font-semibold text-slate-900">{{ __('Registered CR activities') }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ __('The commercial activities your Commercial Registration is licensed for. A company can generally only conduct the activities listed on its own CR.') }}</p>
            </div>
        </div>

        @if (! $activitiesSeemToCoverSoftware)
            <div class="mt-4 rounded-lg bg-red-50 border border-red-100 px-4 py-3 text-sm text-red-700">
                {{ __('None of your registered activities below look like they cover selling software or IT/technology services. Selling SaaS commercially under a CR limited to other activities (e.g. construction) may require adding an e-commerce or IT-services activity to your CR. This is worth confirming with a licensed Saudi business/legal consultant or the Ministry of Commerce before relying on this CR to sell software.') }}
            </div>
        @else
            <div class="mt-4 rounded-lg bg-emerald-50 border border-emerald-100 px-4 py-3 text-sm text-emerald-700">
                {{ __('At least one registered activity below mentions software/technology/IT services. Still worth confirming the exact wording covers SaaS sales with your business advisor.') }}
            </div>
        @endif

        <div class="mt-4 divide-y divide-slate-50 border border-slate-100 rounded-lg overflow-hidden">
            @forelse ($activities as $activity)
                <div class="flex items-center justify-between gap-4 px-4 py-2.5 text-sm">
                    <div>
                        <span class="font-mono text-xs text-slate-400">{{ $activity->code }}</span>
                        <span class="ms-2 text-slate-700">{{ $activity->description_ar }}</span>
                    </div>
                    <form method="POST" action="{{ route('admin.compliance.activities.destroy', $activity) }}" onsubmit="return confirm('{{ __('Remove this activity?') }}')">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-red-500 hover:underline text-xs">{{ __('Remove') }}</button>
                    </form>
                </div>
            @empty
                <p class="px-4 py-4 text-sm text-slate-400">{{ __('No activities recorded yet.') }}</p>
            @endforelse
        </div>

        <form method="POST" action="{{ route('admin.compliance.activities.store') }}" class="mt-4 flex flex-wrap items-end gap-3">
            @csrf
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('CR code') }}</label>
                <input type="text" name="code" required maxlength="20" class="mt-1 w-32 rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div class="flex-1 min-w-[240px]">
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Activity description (Arabic)') }}</label>
                <input type="text" name="description_ar" required dir="rtl" maxlength="500" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
            </div>
            <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Add') }}</button>
        </form>
    </div>

    {{-- ZATCA certification status --}}
    <div class="bg-white rounded-xl border border-slate-100 p-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="font-semibold text-slate-900">{{ __('ZATCA certification status') }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ __('Whether your business itself currently holds a verified ZATCA certification. This is separate from the software\'s e-invoicing feature, which is designed to support ZATCA\'s technical requirements regardless of this setting — see the ZATCA disclaimer document.') }}</p>
            </div>
            @if ($zatcaCertified)
                <span class="shrink-0 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">{{ __('Certified') }}</span>
            @else
                <span class="shrink-0 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">{{ __('Not verified') }}</span>
            @endif
        </div>

        <form method="POST" action="{{ route('admin.compliance.zatca-status.update') }}" class="mt-4 space-y-3">
            @csrf
            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" name="certified" value="1" @checked($zatcaCertified) class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                {{ __('We currently hold a verified ZATCA certification') }}
            </label>
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Note (optional — e.g. certificate reference, date)') }}</label>
                <input type="text" name="note" maxlength="1000" value="{{ old('note', $zatcaCertificationNote) }}" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
            </div>
            <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Save') }}</button>
        </form>
    </div>

    {{-- Legal documents --}}
    <div class="bg-white rounded-xl border border-slate-100 p-6">
        <h2 class="font-semibold text-slate-900">{{ __('Legal documents') }}</h2>
        <p class="mt-1 text-sm text-slate-500">{{ __('Drafted starting points — have each checked by a licensed Saudi legal advisor before relying on it.') }}</p>
        <div class="mt-4 divide-y divide-slate-50 border border-slate-100 rounded-lg overflow-hidden">
            @foreach ($documents as $document)
                <div class="flex items-center justify-between gap-4 px-4 py-2.5 text-sm">
                    <span class="text-slate-700">{{ $document->title_en }}</span>
                    <div class="flex items-center gap-3">
                        @if ($document->requires_legal_review)
                            <span class="rounded-full bg-red-50 px-2 py-0.5 text-xs font-semibold text-red-700">{{ __('Pending review') }}</span>
                        @else
                            <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700">{{ __('Reviewed') }}</span>
                        @endif
                        <a href="{{ route('admin.legal-documents.edit', $document) }}" class="text-brand-700 hover:underline font-medium">{{ __('Edit') }}</a>
                    </div>
                </div>
            @endforeach
        </div>
        <a href="{{ route('admin.legal-documents.index') }}" class="mt-4 inline-block text-sm font-semibold text-brand-700 hover:underline">{{ __('Manage Legal Documents') }} &rarr;</a>
    </div>
</div>
@endsection
