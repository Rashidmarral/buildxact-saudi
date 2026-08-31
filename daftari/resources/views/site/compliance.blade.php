@extends('layouts.site')

@section('title', __('Compliance') . ' · Daftari')

@section('content')
@php($sections = \App\Models\CmsSection::forPage('compliance'))
@foreach ($sections as $section)
    @include('site.partials.cms-section', ['section' => $section])
    @if ($section->type === 'feature_grid')
        <section class="mx-auto max-w-3xl px-6 -mt-8 pb-16">
            <p class="rounded-lg bg-slate-50 border border-slate-100 px-4 py-3 text-sm text-slate-500">
                <strong class="text-slate-700">{{ __('Note:') }}</strong>
                {{ __('This page is informational, to help you understand what generally applies. It is not legal or tax advice — confirm your specific obligations with ZATCA or a licensed advisor.') }}
            </p>
        </section>
    @endif
@endforeach
@endsection
