<x-admin.layout title="Certifications">

    <div class="mb-6 flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-bold text-slate-900">Certifications</h2>
            <p class="mt-1 text-sm text-slate-500">Shown on the Quality &amp; Certifications page.</p>
        </div>
        <a href="{{ route('admin.certifications.create') }}" class="rounded-lg bg-teal-800 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-900">+ Add Certification</a>
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="w-full min-w-[640px] text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3">Title</th>
                    <th class="px-4 py-3">Issuing Body</th>
                    <th class="px-4 py-3">Icon</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($certifications as $certification)
                    <tr>
                        <td class="px-4 py-3 font-medium text-slate-800">{{ $certification->title }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $certification->issuing_body }}</td>
                        <td class="px-4 py-3"><x-icon :name="$certification->icon" class="h-4 w-4 text-teal-700" /></td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('admin.certifications.edit', $certification) }}" class="font-medium text-teal-700 hover:underline">Edit</a>
                            <form action="{{ route('admin.certifications.destroy', $certification) }}" method="POST" class="inline" onsubmit="return confirm('Delete this certification?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="ml-3 font-medium text-red-600 hover:underline">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-10 text-center text-slate-400">No certifications yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

</x-admin.layout>
