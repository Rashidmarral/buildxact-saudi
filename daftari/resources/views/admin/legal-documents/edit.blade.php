@extends('layouts.admin')

@section('title', __('Edit Legal Document'))

@section('content')
<div class="mb-6">
    <a href="{{ route('admin.legal-documents.index') }}" class="text-sm text-slate-500 hover:text-slate-700">&larr; {{ __('Legal Documents') }}</a>
    <h1 class="text-2xl font-bold text-slate-900 mt-1">{{ $document->title_en }}</h1>
</div>

@if ($document->requires_legal_review)
    <div class="mb-6 rounded-lg bg-red-50 border border-red-100 px-4 py-3 text-sm text-red-700">
        {{ __('This is a drafted starting point, not reviewed legal advice. Have it checked by a licensed Saudi legal advisor before you rely on it or rely on it being enforceable, then untick "Needs legal review" below.') }}
    </div>
@endif

<form method="POST" action="{{ route('admin.legal-documents.update', $document) }}" class="space-y-6" x-data="{ locale: 'en' }">
    @csrf
    @method('PUT')

    <div class="flex gap-1 rounded-lg bg-slate-100 p-1 max-w-xs">
        <button type="button" @click="locale = 'en'" :class="locale === 'en' ? 'bg-brand-600 text-white shadow-soft' : 'text-slate-500 hover:bg-slate-50'" class="flex-1 rounded-lg px-4 py-2 text-sm font-semibold transition-colors">{{ __('English content') }}</button>
        <button type="button" @click="locale = 'ar'" :class="locale === 'ar' ? 'bg-brand-600 text-white shadow-soft' : 'text-slate-500 hover:bg-slate-50'" class="flex-1 rounded-lg px-4 py-2 text-sm font-semibold transition-colors">{{ __('Arabic content') }}</button>
    </div>

    <div class="bg-white rounded-xl border border-slate-100 p-6 space-y-4">
        <h2 class="font-semibold text-slate-900 flex items-center gap-2">
            <span class="inline-flex h-6 w-6 items-center justify-center rounded-md bg-brand-50 text-brand-600">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5"><path d="M4 4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4Zm3 2a1 1 0 0 0 0 2h6a1 1 0 1 0 0-2H7Zm0 4a1 1 0 0 0 0 2h6a1 1 0 1 0 0-2H7Zm0 4a1 1 0 1 0 0 2h3a1 1 0 1 0 0-2H7Z"/></svg>
            </span>
            {{ __('Content') }}
        </h2>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Title') }}</label>
            <input x-show="locale === 'en'" type="text" name="title_en" required maxlength="255" value="{{ old('title_en', $document->title_en) }}" placeholder="{{ __('English') }}" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
            <input x-show="locale === 'ar'" type="text" name="title_ar" maxlength="255" value="{{ old('title_ar', $document->title_ar) }}" dir="rtl" placeholder="{{ __('Arabic') }}" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        </div>

        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500 mb-1">{{ __('Body') }}</label>
            <div x-show="locale === 'en'">
                @include('partials.rich-text-editor', ['name' => 'body_en', 'value' => old('body_en', $document->body_en), 'dir' => 'ltr'])
            </div>
            <div x-show="locale === 'ar'">
                @include('partials.rich-text-editor', ['name' => 'body_ar', 'value' => old('body_ar', $document->body_ar), 'dir' => 'rtl'])
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-100 p-6 space-y-4">
        <h2 class="font-semibold text-slate-900 flex items-center gap-2">
            <span class="inline-flex h-6 w-6 items-center justify-center rounded-md bg-brand-50 text-brand-600">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm1-11a1 1 0 1 0-2 0v4a1 1 0 0 0 .293.707l2.828 2.829a1 1 0 1 0 1.415-1.415L11 10.586V7Z" clip-rule="evenodd"/></svg>
            </span>
            {{ __('Publishing') }}
        </h2>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Status') }}</label>
            <select name="status" class="mt-1 w-full max-w-xs rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                <option value="draft" @selected(old('status', $document->status) === 'draft')>{{ __('Draft') }}</option>
                <option value="published" @selected(old('status', $document->status) === 'published')>{{ __('Published') }}</option>
            </select>
            <p class="mt-1 text-xs text-slate-400">{{ __('Published documents appear at their public link and can be added to the footer from Website CMS. Draft documents stay reachable to you for review but are not linked anywhere public.') }}</p>
        </div>
        <label class="flex items-center gap-2 text-sm text-slate-600">
            <input type="checkbox" name="requires_legal_review" value="1" @checked(old('requires_legal_review', $document->requires_legal_review)) class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
            {{ __('Needs legal review') }}
        </label>
    </div>

    <button type="submit" class="rounded-lg bg-brand-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Save') }}</button>
</form>
@endsection
