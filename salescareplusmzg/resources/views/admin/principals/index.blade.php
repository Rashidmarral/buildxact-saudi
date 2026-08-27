<x-admin.layout title="Principals">

    <div class="mb-6 flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-bold text-slate-900">Principals</h2>
            <p class="mt-1 text-sm text-slate-500">Manufacturer partners shown on the Principals page.</p>
        </div>
        <a href="{{ route('admin.principals.create') }}" class="rounded-lg bg-teal-800 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-900">+ Add Principal</a>
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="w-full min-w-[720px] text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3">Name</th>
                    <th class="px-4 py-3">Tagline</th>
                    <th class="px-4 py-3">Years</th>
                    <th class="px-4 py-3">Products</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($principals as $principal)
                    <tr>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                @if ($principal->logo_path)
                                    <img src="{{ asset('storage/'.$principal->logo_path) }}" class="h-9 w-9 rounded-lg object-cover" alt="">
                                @else
                                    <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-teal-50 text-xs font-bold text-teal-800">{{ $principal->initials }}</span>
                                @endif
                                <span class="font-medium text-slate-800">{{ $principal->name }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-slate-500">{{ $principal->tagline }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $principal->years_partnership }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $principal->products_count }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('admin.principals.edit', $principal) }}" class="font-medium text-teal-700 hover:underline">Edit</a>
                            <form action="{{ route('admin.principals.destroy', $principal) }}" method="POST" class="inline" onsubmit="return confirm('Delete this principal?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="ml-3 font-medium text-red-600 hover:underline">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-slate-400">No principals yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

</x-admin.layout>
