@extends('layouts.admin')

@section('title', __('Website CMS'))

@section('content')
<div class="mb-6">
    <p class="text-sm text-slate-500">{{ __('Manage the content blocks that make up each public marketing page — no deploy needed.') }}</p>
</div>

<div class="mb-6 flex flex-wrap gap-2 border-b border-slate-100 pb-4">
    @foreach ($pages as $p)
        <a href="{{ route('admin.cms.pages.show', $p) }}" class="rounded-lg px-3.5 py-2 text-sm font-semibold {{ $p === $page ? 'bg-brand-600 text-white' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50' }}">
            {{ \App\Models\CmsSection::pageLabel($p) }}
        </a>
    @endforeach
</div>

<div class="space-y-4">
    @forelse ($sections as $section)
        <div class="flex items-center justify-between gap-4 rounded-xl border border-slate-100 bg-white px-5 py-4">
            <div class="flex min-w-0 items-center gap-4">
                <div class="flex flex-col gap-1">
                    <form method="POST" action="{{ route('admin.cms.sections.move', $section) }}">
                        @csrf
                        <input type="hidden" name="direction" value="up">
                        <button type="submit" class="text-slate-400 hover:text-slate-700" title="{{ __('Move up') }}">▲</button>
                    </form>
                    <form method="POST" action="{{ route('admin.cms.sections.move', $section) }}">
                        @csrf
                        <input type="hidden" name="direction" value="down">
                        <button type="submit" class="text-slate-400 hover:text-slate-700" title="{{ __('Move down') }}">▼</button>
                    </form>
                </div>
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold uppercase tracking-wide text-slate-600">{{ str_replace('_', ' ', $section->type) }}</span>
                        @if (! $section->is_active)
                            <span class="rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-700">{{ __('Hidden') }}</span>
                        @endif
                    </div>
                    <p class="mt-1 truncate font-medium text-slate-800">{{ $section->title_en ?: $section->badge_en ?: __('(untitled)') }}</p>
                    @if ($section->hasItems())
                        <p class="text-xs text-slate-400">{{ trans_choice(':count item|:count items', $section->items_count, ['count' => $section->items_count]) }}</p>
                    @endif
                </div>
            </div>
            <div class="flex shrink-0 items-center gap-3">
                <a href="{{ route('admin.cms.sections.edit', $section) }}" class="text-sm font-semibold text-brand-700 hover:underline">{{ __('Edit') }}</a>
                <form method="POST" action="{{ route('admin.cms.sections.destroy', $section) }}" onsubmit="return confirm('{{ __('Remove this section?') }}')">
                    @csrf @method('DELETE')
                    <button type="submit" class="text-sm font-semibold text-red-600 hover:underline">{{ __('Remove') }}</button>
                </form>
            </div>
        </div>
    @empty
        <div class="rounded-xl border border-dashed border-slate-200 bg-white px-6 py-10 text-center text-sm text-slate-500">
            {{ __('No sections yet on this page.') }}
        </div>
    @endforelse
</div>

<div class="mt-6 rounded-xl border border-slate-100 bg-white p-5">
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
        <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('+ Add section') }}</button>
    </form>
</div>
@endsection
