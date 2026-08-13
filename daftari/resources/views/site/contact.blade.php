@extends('layouts.site')

@section('title', __('Contact') . ' · Daftari')

@section('content')
<section class="mx-auto max-w-2xl px-6 py-16">
    <h1 class="text-4xl font-extrabold text-slate-900 text-center">{{ __('Get in touch') }}</h1>
    <p class="mt-4 text-lg text-slate-600 text-center">{{ __('Questions about Daftari or your subscription? Send us a message.') }}</p>

    @if ($errors->any())
        <div class="mt-8 rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">
            <ul class="list-disc ps-4 space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('contact.submit') }}" class="mt-8 space-y-5 bg-white rounded-2xl border border-slate-100 p-8">
        @csrf
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Name') }}</label>
            <input type="text" name="name" value="{{ old('name') }}" required class="mt-1 w-full rounded-lg border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Email') }}</label>
            <input type="email" name="email" value="{{ old('email') }}" required class="mt-1 w-full rounded-lg border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Message') }}</label>
            <textarea name="message" rows="5" required class="mt-1 w-full rounded-lg border-slate-200 focus:border-brand-500 focus:ring-brand-500">{{ old('message') }}</textarea>
        </div>
        <button type="submit" class="w-full rounded-lg bg-brand-600 px-6 py-3 font-semibold text-white hover:bg-brand-700">{{ __('Send message') }}</button>
    </form>
</section>
@endsection
