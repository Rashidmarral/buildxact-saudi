<x-admin.layout title="Navigation">

    <div class="mb-6 flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-bold text-slate-900">Navigation</h2>
            <p class="mt-1 text-sm text-slate-500">Controls the header menu, "More" dropdown, and footer link columns.</p>
        </div>
        <a href="{{ route('admin.nav-items.create') }}" class="rounded-lg bg-teal-800 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-900">+ Add Link</a>
    </div>

    @foreach (\App\Http\Controllers\Admin\NavItemController::LOCATIONS as $location => $locationLabel)
        <div class="mb-6 overflow-x-auto rounded-xl border border-slate-200 bg-white">
            <div class="border-b border-slate-100 px-4 py-3 text-sm font-semibold text-slate-800">{{ $locationLabel }}</div>
            <table class="w-full min-w-[640px] text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Label</th>
                        <th class="px-4 py-3">Links To</th>
                        <th class="px-4 py-3">Visible</th>
                        <th class="px-4 py-3">Order</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($navItems->get($location, []) as $item)
                        <tr>
                            <td class="px-4 py-3 font-medium text-slate-800">{{ $item->label }}</td>
                            <td class="px-4 py-3 text-slate-500">
                                @if ($item->url)
                                    <span class="font-mono text-xs">{{ $item->url }}</span>
                                @elseif ($item->page)
                                    Page: {{ $item->page->title }}
                                @else
                                    Route: {{ $item->route_name }}
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @if ($item->is_visible)
                                    <span class="text-xs font-medium text-emerald-600">Visible</span>
                                @else
                                    <span class="text-xs font-medium text-slate-400">Hidden</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-500">{{ $item->sort_order }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('admin.nav-items.edit', $item) }}" class="font-medium text-teal-700 hover:underline">Edit</a>
                                <form action="{{ route('admin.nav-items.destroy', $item) }}" method="POST" class="inline" onsubmit="return confirm('Delete this link?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="ml-3 font-medium text-red-600 hover:underline">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-6 text-center text-slate-400">No links here yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endforeach

</x-admin.layout>
