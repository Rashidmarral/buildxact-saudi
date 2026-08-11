<x-admin.layout :title="$meta['label']">

    <a href="{{ route('admin.content-items.groups') }}" class="text-sm font-medium text-teal-700 hover:underline">&larr; Back to Page Content</a>

    <div class="mb-6 mt-3 flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-bold text-slate-900">{{ $meta['label'] }}</h2>
            <p class="mt-1 text-sm text-slate-500">{{ $meta['page'] }}</p>
        </div>
        <a href="{{ route('admin.content-items.create', $group) }}" class="rounded-lg bg-teal-800 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-900">+ Add Item</a>
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="w-full min-w-[640px] text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3">Title</th>
                    @if ($meta['subtitle_label'])<th class="px-4 py-3">Subtitle</th>@endif
                    <th class="px-4 py-3">Order</th>
                    <th class="px-4 py-3">Visible</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($items as $item)
                    <tr>
                        <td class="px-4 py-3 font-medium text-slate-800">{{ $item->title }}</td>
                        @if ($meta['subtitle_label'])<td class="px-4 py-3 text-slate-500">{{ $item->subtitle }}</td>@endif
                        <td class="px-4 py-3 text-slate-500">{{ $item->sort_order }}</td>
                        <td class="px-4 py-3">
                            @if ($item->is_visible)
                                <span class="text-xs font-medium text-emerald-600">Visible</span>
                            @else
                                <span class="text-xs font-medium text-slate-400">Hidden</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('admin.content-items.edit', [$group, $item]) }}" class="font-medium text-teal-700 hover:underline">Edit</a>
                            <form action="{{ route('admin.content-items.destroy', [$group, $item]) }}" method="POST" class="inline" onsubmit="return confirm('Delete this item?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="ml-3 font-medium text-red-600 hover:underline">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-slate-400">No items yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

</x-admin.layout>
