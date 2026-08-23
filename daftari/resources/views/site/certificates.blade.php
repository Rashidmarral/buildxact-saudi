@extends('layouts.site')

@section('title', __('Certificates') . ' · Daftari')

@section('content')
<section class="mx-auto max-w-4xl px-6 py-16">
    <h1 class="text-4xl font-extrabold text-slate-900">{{ __('Certificates & compliance documents') }}</h1>
    <p class="mt-4 text-lg text-slate-600">{{ __('Official certificates and documents relevant to our compliance with the rules and regulations of the Kingdom of Saudi Arabia.') }}</p>
</section>

<section class="mx-auto max-w-5xl px-6 pb-20">
    @if ($documents->isEmpty())
        <p class="rounded-xl border border-slate-100 bg-slate-50 px-6 py-8 text-center text-sm text-slate-500">{{ __('No certificates have been published yet.') }}</p>
    @else
        <div class="grid gap-6 md:grid-cols-2">
            @foreach ($documents as $document)
                <a href="{{ Storage::url($document->file_path) }}" target="_blank" class="block rounded-xl border border-slate-100 bg-white p-6 transition-shadow hover:shadow-card">
                    <div class="flex items-start gap-4">
                        <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-700">
                            @include('partials.icon', ['name' => 'templates', 'class' => 'h-5 w-5'])
                        </span>
                        <div class="min-w-0">
                            <h3 class="font-semibold text-slate-900">{{ $document->titleFor(app()->getLocale()) }}</h3>
                            @if ($document->description)
                                <p class="mt-1 text-sm text-slate-500">{{ $document->description }}</p>
                            @endif
                            <span class="mt-3 inline-block text-sm font-medium text-brand-700">{{ __('View document') }} &rarr;</span>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</section>
@endsection
