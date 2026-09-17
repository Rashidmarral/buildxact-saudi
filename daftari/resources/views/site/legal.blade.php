@extends('layouts.site')

@section('title', $document->title() . ' · Daftari')

@section('content')
<section class="mx-auto max-w-3xl px-6 py-16">
    <h1 class="text-3xl font-extrabold text-slate-900">{{ $document->title() }}</h1>
    <p class="mt-4 text-slate-500 text-sm">{{ __('Last updated') }}: {{ $document->updated_at->format('Y-m-d') }}</p>

    @if ($document->requires_legal_review)
        <div class="mt-6 rounded-lg bg-amber-50 border border-amber-100 px-4 py-3 text-sm text-amber-800">
            {{ __('This document is a drafted starting point and has not yet been reviewed by a licensed legal advisor. It is provided for informational purposes and should not be relied on as final or legally binding until reviewed and confirmed.') }}
        </div>
    @endif

    <div class="prose-cms mt-8 text-slate-600">
        {!! $document->bodyHtml() !!}
    </div>
</section>
@endsection
