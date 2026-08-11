<x-admin.layout title="Pages">

    <div class="mb-6 flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-bold text-slate-900">Pages</h2>
            <p class="mt-1 text-sm text-slate-500">Build brand-new pages with their own URL and sections — no code required.</p>
        </div>
        <a href="{{ route('admin.pages.create') }}" class="rounded-lg bg-teal-800 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-900">+ Add Page</a>
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="w-full min-w-[720px] text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3">Title</th>
                    <th class="px-4 py-3">URL</th>
                    <th class="px-4 py-3">Sections</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($pages as $page)
                    <tr>
                        <td class="px-4 py-3 font-medium text-slate-800">{{ $page->title }}</td>
                        <td class="px-4 py-3 text-slate-500 font-mono text-xs">/{{ $page->slug }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $page->sections_count }}</td>
                        <td class="px-4 py-3">
                            @if ($page->is_published)
                                <span class="text-xs font-medium text-emerald-600">Published</span>
                            @else
                                <span class="text-xs font-medium text-slate-400">Draft</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            @if ($page->is_published)
                                <a href="{{ route('page.show', $page->slug) }}" target="_blank" class="font-medium text-slate-500 hover:underline">View</a>
                            @endif
                            <a href="{{ route('admin.pages.edit', $page) }}" class="ml-3 font-medium text-teal-700 hover:underline">Edit</a>
                            <form action="{{ route('admin.pages.destroy', $page) }}" method="POST" class="inline" onsubmit="return confirm('Delete this page and all its sections?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="ml-3 font-medium text-red-600 hover:underline">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-slate-400">No custom pages yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

</x-admin.layout>
