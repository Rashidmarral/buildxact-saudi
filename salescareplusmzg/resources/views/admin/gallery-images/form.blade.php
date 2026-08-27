@php $editing = $galleryImage->exists; @endphp
<x-admin.layout :title="$editing ? 'Edit Gallery Image' : 'Add Gallery Image'">

    <a href="{{ route('admin.gallery-images.index') }}" class="text-sm font-medium text-teal-700 hover:underline">&larr; Back to Gallery</a>

    <form method="POST" action="{{ $editing ? route('admin.gallery-images.update', $galleryImage) : route('admin.gallery-images.store') }}" enctype="multipart/form-data" class="mt-4 max-w-xl space-y-5 rounded-xl border border-slate-200 bg-white p-6">
        @csrf
        @if ($editing) @method('PUT') @endif

        <x-admin.field label="Title" name="title" :value="$galleryImage->title" required />
        <x-admin.field label="Caption" name="caption" :value="$galleryImage->caption" />

        <div>
            <x-admin.field label="Photo" name="image" type="file" accept="image/*" />
            @if ($galleryImage->image_path)
                <img src="{{ asset('storage/'.$galleryImage->image_path) }}" class="mt-2 h-24 w-24 rounded-lg border border-slate-200 object-cover" alt="">
            @elseif ($galleryImage->illustration)
                <p class="mt-2 text-xs text-slate-400">Currently showing a placeholder illustration — upload a real photo to replace it.</p>
            @endif
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <x-admin.field label="Sort Order" name="sort_order" type="number" :value="$galleryImage->sort_order ?? 0" />
            <div class="pt-6">
                <x-admin.field label="" name="is_visible" type="checkbox" :value="$galleryImage->exists ? $galleryImage->is_visible : true" hint="Visible on the Gallery page" />
            </div>
        </div>

        <button type="submit" class="rounded-lg bg-teal-800 px-5 py-2.5 text-sm font-semibold text-white hover:bg-teal-900">
            {{ $editing ? 'Save Changes' : 'Add Image' }}
        </button>
    </form>

</x-admin.layout>
