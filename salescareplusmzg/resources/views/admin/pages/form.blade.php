@php $editing = $page->exists; @endphp
<x-admin.layout :title="$editing ? 'Edit Page' : 'Add Page'">

    <a href="{{ route('admin.pages.index') }}" class="text-sm font-medium text-teal-700 hover:underline">&larr; Back to Pages</a>

    <form method="POST" action="{{ $editing ? route('admin.pages.update', $page) : route('admin.pages.store') }}" class="mt-4 max-w-2xl space-y-5 rounded-xl border border-slate-200 bg-white p-6">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="grid gap-5 sm:grid-cols-2">
            <x-admin.field label="Page Title" name="title" :value="$page->title" required />
            <x-admin.field label="URL Slug" name="slug" :value="$page->slug" hint="Leave blank to auto-generate from title. e.g. our-story" />
        </div>

        <x-admin.field label="Meta Description" name="meta_description" type="textarea" rows="2" :value="$page->meta_description" hint="Shown in search engine results." />

        <div class="grid gap-5 sm:grid-cols-2">
            <x-admin.field label="Sort Order" name="sort_order" type="number" :value="$page->sort_order ?? 0" />
            <x-admin.field label="" name="is_published" type="checkbox" :value="$page->exists ? $page->is_published : true" hint="Published (visible to the public)" />
        </div>

        <button type="submit" class="rounded-lg bg-teal-800 px-5 py-2.5 text-sm font-semibold text-white hover:bg-teal-900">
            {{ $editing ? 'Save Page Details' : 'Create Page' }}
        </button>
    </form>

    @if ($editing)
        <div class="mt-8 max-w-2xl">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-slate-900">Sections</h3>
                <a href="{{ route('admin.pages.sections.create', $page) }}" class="rounded-lg bg-coral-500 px-4 py-2 text-sm font-semibold text-white hover:bg-coral-600">+ Add Section</a>
            </div>

            <div class="space-y-3">
                @forelse ($page->sections as $section)
                    <div class="flex items-center justify-between rounded-xl border border-slate-200 bg-white px-4 py-3">
                        <div>
                            <p class="font-medium text-slate-800">{{ \App\Models\PageSection::TYPES[$section->type] ?? $section->type }}</p>
                            <p class="text-xs text-slate-400">{{ $section->heading ?: 'No heading' }} &middot; order {{ $section->sort_order }} &middot; {{ $section->is_visible ? 'visible' : 'hidden' }}</p>
                        </div>
                        <div class="flex items-center gap-3 text-sm">
                            <a href="{{ route('admin.pages.sections.edit', [$page, $section]) }}" class="font-medium text-teal-700 hover:underline">Edit</a>
                            <form action="{{ route('admin.pages.sections.destroy', [$page, $section]) }}" method="POST" onsubmit="return confirm('Delete this section?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="font-medium text-red-600 hover:underline">Delete</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <p class="rounded-xl border border-dashed border-slate-300 px-4 py-8 text-center text-sm text-slate-400">
                        No sections yet — add a hero, rich text block, image + text, card grid, or gallery above.
                    </p>
                @endforelse
            </div>
        </div>
    @endif

</x-admin.layout>
