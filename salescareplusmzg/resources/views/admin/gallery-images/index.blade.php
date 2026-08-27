<x-admin.layout title="Gallery">

    <div class="mb-6 flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-bold text-slate-900">Gallery</h2>
            <p class="mt-1 text-sm text-slate-500">Photos shown on the public Gallery page.</p>
        </div>
        <a href="{{ route('admin.gallery-images.create') }}" class="rounded-lg bg-teal-800 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-900">+ Add Image</a>
    </div>

    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
        @forelse ($galleryImages as $image)
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                <div class="flex aspect-[4/3] items-center justify-center bg-teal-50">
                    @if ($image->image_path)
                        <img src="{{ asset('storage/'.$image->image_path) }}" alt="{{ $image->title }}" class="h-full w-full object-cover">
                    @elseif ($image->illustration)
                        <x-illustration :name="$image->illustration" class="h-full w-full p-4" />
                    @else
                        <x-icon name="building" class="h-10 w-10 text-teal-300" />
                    @endif
                </div>
                <div class="p-3">
                    <p class="truncate text-sm font-semibold text-slate-800">{{ $image->title }}</p>
                    <p class="mt-0.5 text-xs {{ $image->is_visible ? 'text-emerald-600' : 'text-slate-400' }}">{{ $image->is_visible ? 'Visible' : 'Hidden' }}</p>
                    <div class="mt-2 flex items-center justify-between text-xs">
                        <a href="{{ route('admin.gallery-images.edit', $image) }}" class="font-medium text-teal-700 hover:underline">Edit</a>
                        <form action="{{ route('admin.gallery-images.destroy', $image) }}" method="POST" onsubmit="return confirm('Delete this image?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="font-medium text-red-600 hover:underline">Delete</button>
                        </form>
                    </div>
                </div>
            </div>
        @empty
            <p class="col-span-full rounded-xl border border-dashed border-slate-300 px-4 py-10 text-center text-sm text-slate-400">No gallery images yet.</p>
        @endforelse
    </div>

</x-admin.layout>
