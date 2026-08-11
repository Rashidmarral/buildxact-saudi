@php
    $editing = $section->exists;
    $itemsRaw = $section->items ? collect($section->items)->map(fn ($item) => ($item['heading'] ?? '').' | '.($item['text'] ?? ''))->implode("\n") : '';
@endphp
<x-admin.layout :title="$editing ? 'Edit Section' : 'Add Section'">

    <a href="{{ route('admin.pages.edit', $page) }}" class="text-sm font-medium text-teal-700 hover:underline">&larr; Back to {{ $page->title }}</a>

    <form method="POST" action="{{ $editing ? route('admin.pages.sections.update', [$page, $section]) : route('admin.pages.sections.store', $page) }}" enctype="multipart/form-data" class="mt-4 max-w-2xl space-y-5 rounded-xl border border-slate-200 bg-white p-6">
        @csrf
        @if ($editing) @method('PUT') @endif

        <x-admin.field label="Section Type" name="type" type="select" required>
            @foreach (\App\Models\PageSection::TYPES as $value => $typeLabel)
                <option value="{{ $value }}" @selected(old('type', $section->type ?: 'rich_text') === $value)>{{ $typeLabel }}</option>
            @endforeach
        </x-admin.field>

        <div class="grid gap-5 sm:grid-cols-2">
            <x-admin.field label="Heading" name="heading" :value="$section->heading" hint="Used by: Hero, Image+Text, CTA, Card Grid, Gallery" />
            <x-admin.field label="Subheading" name="subheading" :value="$section->subheading" />
        </div>

        <x-admin.field label="Body Text" name="body" type="textarea" rows="5" :value="$section->body" hint="Used by: Hero, Rich Text, Image+Text, CTA" />

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <x-admin.field label="Image" name="image" type="file" accept="image/*" hint="Used by: Image+Text, Gallery" />
                @if ($section->image_path)
                    <img src="{{ asset('storage/'.$section->image_path) }}" class="mt-2 h-16 rounded-lg border border-slate-200 object-cover" alt="">
                @endif
            </div>
            <div>
                <x-admin.field label="Video File" name="video" type="file" accept="video/*" hint="Used by: Hero with Video Banner" />
                @if ($section->video_path)
                    <p class="mt-2 text-xs text-emerald-600">Uploaded: {{ basename($section->video_path) }}</p>
                @endif
            </div>
        </div>

        <x-admin.field label="Video URL" name="video_url" :value="$section->video_url" hint="Alternative to uploading — a direct .mp4 link. Used by: Hero with Video Banner" />

        <div class="grid gap-5 sm:grid-cols-2">
            <x-admin.field label="Button Text" name="button_text" :value="$section->button_text" hint="Used by: Hero, CTA" />
            <x-admin.field label="Button URL" name="button_url" :value="$section->button_url" />
        </div>

        <x-admin.field label="Card / Gallery Items" name="items_raw" type="textarea" rows="4" :value="$itemsRaw" hint='Used by: Card Grid, Gallery. One item per line, formatted as "Heading | Text"' />

        <div class="grid gap-5 sm:grid-cols-3">
            <x-admin.field label="Background" name="background" type="select">
                @foreach (['white' => 'White', 'tint' => 'Tinted', 'dark' => 'Dark'] as $value => $bgLabel)
                    <option value="{{ $value }}" @selected(old('background', $section->background ?: 'white') === $value)>{{ $bgLabel }}</option>
                @endforeach
            </x-admin.field>
            <x-admin.field label="Sort Order" name="sort_order" type="number" :value="$section->sort_order ?? 0" />
            <div class="pt-6">
                <x-admin.field label="" name="is_visible" type="checkbox" :value="$section->exists ? $section->is_visible : true" hint="Visible" />
            </div>
        </div>

        <button type="submit" class="rounded-lg bg-teal-800 px-5 py-2.5 text-sm font-semibold text-white hover:bg-teal-900">
            {{ $editing ? 'Save Section' : 'Add Section' }}
        </button>
    </form>

</x-admin.layout>
