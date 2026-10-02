@extends('layouts.site')

@section('title', $page->name() . ' · Daftari')

@section('content')
@forelse ($sections as $section)
    @include('site.partials.cms-section', ['section' => $section])
@empty
    <section class="mx-auto max-w-4xl px-6 py-16 text-center">
        <h1 class="text-4xl font-extrabold text-slate-900">{{ $page->name() }}</h1>
        <p class="mt-4 text-slate-500">{{ __('This page has no content yet.') }}</p>
    </section>
@endforelse
@endsection
