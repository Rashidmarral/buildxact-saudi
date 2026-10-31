@extends('layouts.admin')

@section('title', __('Website CMS'))

@section('content')
<div x-data="{ newPageOpen: false, pageSettingsOpen: false }">
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <p class="max-w-2xl text-sm text-slate-500">{{ __('Manage every public marketing page — including creating brand new ones — and the content blocks on each, with no deploy needed.') }}</p>
        <button type="button" @click="newPageOpen = !newPageOpen" class="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-soft transition-all hover:-translate-y-0.5 hover:bg-brand-700 hover:shadow-card">
            @include('partials.icon', ['name' => 'plus', 'class' => 'h-4 w-4'])
            {{ __('New page') }}
        </button>
    </div>

    <div x-show="newPageOpen" x-transition x-cloak class="mb-6 rounded-2xl border border-brand-100 bg-brand-50/40 p-6 shadow-card">
        <h2 class="font-semibold text-slate-900">{{ __('Create a new page') }}</h2>
        <form method="POST" action="{{ route('admin.cms.pages.store') }}" class="mt-4 grid gap-4 md:grid-cols-2">
            @csrf
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Page name (English)') }}</label>
                <input type="text" name="name_en" value="{{ old('name_en') }}" required placeholder="{{ __('e.g. Our Story') }}" class="mt-1.5 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                @error('name_en')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Page name (Arabic)') }}</label>
                <input type="text" name="name_ar" value="{{ old('name_ar') }}" dir="rtl" placeholder="{{ __('e.g. قصتنا') }}" class="mt-1.5 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Page address (optional)') }}</label>
                <div class="mt-1.5 flex items-center rounded-lg border border-slate-200 bg-white text-sm focus-within:border-brand-500 focus-within:ring-1 focus-within:ring-brand-500">
                    <span class="ps-3 text-slate-400">{{ url('pages').'/' }}</span>
                    <input type="text" name="slug" value="{{ old('slug') }}" placeholder="{{ __('auto-generated from the name') }}" class="w-full border-0 bg-transparent text-sm focus:ring-0">
                </div>
                @error('slug')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div class="flex flex-wrap items-end gap-x-6 gap-y-2">
                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <input type="checkbox" name="show_in_footer" value="1" checked class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                    {{ __('Show a link to this page in the site footer') }}
                </label>
                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <input type="checkbox" name="show_in_menu" value="1" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                    {{ __('Show a link to this page in the header menu') }}
                </label>
            </div>
            <div class="flex items-center gap-3 md:col-span-2">
                <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Create page') }}</button>
                <button type="button" @click="newPageOpen = false" class="text-sm font-semibold text-slate-500 hover:text-slate-700">{{ __('Cancel') }}</button>
            </div>
        </form>
    </div>

    <div class="mb-6 flex gap-2 overflow-x-auto pb-1 [-ms-overflow-style:none] [scrollbar-width:thin]">
        @foreach ($pages as $p)
            <a href="{{ route('admin.cms.pages.show', $p->slug) }}" class="inline-flex shrink-0 items-center gap-1.5 whitespace-nowrap rounded-lg px-3.5 py-2 text-sm font-semibold transition-colors {{ $p->slug === $page ? 'bg-brand-600 text-white shadow-soft' : 'border border-slate-200 bg-white text-slate-600 hover:border-brand-200 hover:bg-slate-50' }}">
                {{ $p->name() }}
                @unless ($p->is_system)
                    <span class="inline-block h-1.5 w-1.5 rounded-full {{ $p->slug === $page ? 'bg-white/70' : 'bg-brand-400' }}"></span>
                @endunless
            </a>
        @endforeach
    </div>

    @unless ($cmsPage->is_system)
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-100 bg-white p-4 shadow-card">
            <div class="flex flex-wrap items-center gap-3">
                <span class="rounded-full bg-brand-50 px-2.5 py-1 text-xs font-semibold text-brand-700">{{ __('Custom page') }}</span>
                <a href="{{ $cmsPage->publicUrl() }}" target="_blank" class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-brand-700">
                    {{ $cmsPage->publicUrl() }}
                    @include('partials.icon', ['name' => 'arrow-right', 'class' => 'h-3.5 w-3.5 -rotate-45'])
                </a>
                @if (! $cmsPage->is_active)
                    <span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700">{{ __('Page hidden') }}</span>
                @endif
            </div>
            <div class="flex items-center gap-4">
                <button type="button" @click="pageSettingsOpen = !pageSettingsOpen" class="text-sm font-semibold text-slate-600 hover:text-brand-700">{{ __('Page settings') }}</button>
                <form method="POST" action="{{ route('admin.cms.pages.destroy', $cmsPage) }}" onsubmit="return confirm('{{ __('Delete this entire page, including all its sections? This cannot be undone.') }}')">
                    @csrf @method('DELETE')
                    <button type="submit" class="text-sm font-semibold text-red-600 hover:underline">{{ __('Delete page') }}</button>
                </form>
            </div>
        </div>

        <div x-show="pageSettingsOpen" x-transition x-cloak class="mb-6 rounded-2xl border border-slate-100 bg-white p-6 shadow-card">
            <form method="POST" action="{{ route('admin.cms.pages.update', $cmsPage) }}" class="grid gap-4 md:grid-cols-2">
                @csrf @method('PUT')
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Page name (English)') }}</label>
                    <input type="text" name="name_en" value="{{ old('name_en', $cmsPage->name_en) }}" required class="mt-1.5 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Page name (Arabic)') }}</label>
                    <input type="text" name="name_ar" value="{{ old('name_ar', $cmsPage->name_ar) }}" dir="rtl" class="mt-1.5 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                </div>
                <div class="flex items-center gap-6 md:col-span-2">
                    <label class="flex items-center gap-2 text-sm text-slate-600">
                        <input type="checkbox" name="is_active" value="1" @checked($cmsPage->is_active) class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                        {{ __('Page is live') }}
                    </label>
                    <label class="flex items-center gap-2 text-sm text-slate-600">
                        <input type="checkbox" name="show_in_footer" value="1" @checked($cmsPage->show_in_footer) class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                        {{ __('Show a link to this page in the site footer') }}
                    </label>
                    <label class="flex items-center gap-2 text-sm text-slate-600">
                        <input type="checkbox" name="show_in_menu" value="1" @checked($cmsPage->show_in_menu) class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                        {{ __('Show a link to this page in the header menu') }}
                    </label>
                </div>
                <div class="md:col-span-2">
                    <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Save page settings') }}</button>
                </div>
            </form>
        </div>
    @endunless

    <div class="space-y-3">
        @forelse ($sections as $section)
            @php($meta = \App\Models\CmsSection::typeMeta($section->type))
            <div class="card-hover flex items-center justify-between gap-4 rounded-2xl border border-slate-100 bg-white p-4 shadow-card">
                <div class="flex min-w-0 items-center gap-4">
                    <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br {{ $meta['accent'] }} text-white">
                        @include('partials.icon', ['name' => $meta['icon'], 'class' => 'h-5 w-5'])
                    </span>
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold uppercase tracking-wide text-slate-600">{{ str_replace('_', ' ', $section->type) }}</span>
                            @if ($section->is_active)
                                <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700">{{ __('Live') }}</span>
                            @else
                                <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-500">{{ __('Hidden') }}</span>
                            @endif
                            @if ($section->hasItems())
                                <span class="text-xs text-slate-400">{{ trans_choice(':count item|:count items', $section->items_count, ['count' => $section->items_count]) }}</span>
                            @endif
                        </div>
                        <p class="mt-1 truncate font-medium text-slate-800">{{ $section->title_en ?: $section->badge_en ?: $section->subtitle_en ?: __('(untitled)') }}</p>
                    </div>
                </div>
                <div class="flex shrink-0 items-center gap-1">
                    <div class="me-2 hidden flex-col sm:flex">
                        <form method="POST" action="{{ route('admin.cms.sections.move', $section) }}">
                            @csrf
                            <input type="hidden" name="direction" value="up">
                            <button type="submit" class="flex h-6 w-6 items-center justify-center rounded-md text-slate-400 hover:bg-slate-100 hover:text-slate-700" title="{{ __('Move up') }}">
                                <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M14.77 12.79a.75.75 0 01-1.06-.02L10 9.06l-3.71 3.71a.75.75 0 11-1.06-1.06l4.25-4.25a.75.75 0 011.06 0l4.25 4.25a.75.75 0 01-.02 1.06z" clip-rule="evenodd"/></svg>
                            </button>
                        </form>
                        <form method="POST" action="{{ route('admin.cms.sections.move', $section) }}">
                            @csrf
                            <input type="hidden" name="direction" value="down">
                            <button type="submit" class="flex h-6 w-6 items-center justify-center rounded-md text-slate-400 hover:bg-slate-100 hover:text-slate-700" title="{{ __('Move down') }}">
                                <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>
                            </button>
                        </form>
                    </div>
                    <a href="{{ route('admin.cms.sections.edit', $section) }}" class="rounded-lg px-3 py-1.5 text-sm font-semibold text-brand-700 hover:bg-brand-50">{{ __('Edit') }}</a>
                    <form method="POST" action="{{ route('admin.cms.sections.destroy', $section) }}" onsubmit="return confirm('{{ __('Remove this section?') }}')">
                        @csrf @method('DELETE')
                        <button type="submit" class="rounded-lg px-3 py-1.5 text-sm font-semibold text-red-600 hover:bg-red-50">{{ __('Remove') }}</button>
                    </form>
                </div>
            </div>
        @empty
            <div class="rounded-2xl border border-dashed border-slate-200 bg-white px-6 py-14 text-center shadow-card">
                @include('partials.icon', ['name' => 'templates', 'class' => 'mx-auto h-8 w-8 text-slate-300'])
                <p class="mt-3 text-sm text-slate-500">{{ __('No sections yet on this page.') }}</p>
            </div>
        @endforelse
    </div>

    @if (count($types))
        <div class="mt-6 rounded-2xl border border-slate-100 bg-white p-5 shadow-card">
            <form method="POST" action="{{ route('admin.cms.sections.store', $page) }}" class="flex flex-wrap items-end gap-3">
                @csrf
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('Add a section') }}</label>
                    <select name="type" required class="mt-1 w-56 rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                        @foreach ($types as $type)
                            <option value="{{ $type }}">{{ str_replace('_', ' ', ucfirst($type)) }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">
                    @include('partials.icon', ['name' => 'plus', 'class' => 'h-4 w-4'])
                    {{ __('Add section') }}
                </button>
            </form>
        </div>
    @endif
</div>
@endsection
