@extends('layouts.site')

@section('title', __('Contact') . ' · Daftari')

@section('content')
@php($sections = \App\Models\CmsSection::forPage('contact'))
@foreach ($sections as $section)
    @include('site.partials.cms-section', ['section' => $section])
@endforeach

<section class="mx-auto max-w-2xl px-6 pb-16">
    <div class="rounded-xl border border-slate-100 bg-slate-50 px-6 py-4 text-center text-sm text-slate-500">
        {{ __("We'd love to hear from you. Our team responds during business days as quickly as possible.") }}
    </div>
</section>

<section class="mx-auto max-w-2xl px-6 pb-16">
    @if ($errors->any())
        <div class="mb-8 rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">
            <ul class="list-disc ps-4 space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('contact.submit') }}" class="space-y-5 bg-white rounded-2xl border border-slate-100 p-8">
        @csrf
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Name') }}</label>
            <input type="text" name="name" value="{{ old('name') }}" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Email') }}</label>
            <input type="email" name="email" value="{{ old('email') }}" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Message') }}</label>
            <textarea name="message" rows="5" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">{{ old('message') }}</textarea>
        </div>
        <button type="submit" class="w-full rounded-lg bg-brand-600 px-6 py-3 font-semibold text-white hover:bg-brand-700">{{ __('Send message') }}</button>
    </form>
</section>
@endsection
