<x-admin.layout title="FAQs">

    <div class="mb-6 flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-bold text-slate-900">FAQs</h2>
            <p class="mt-1 text-sm text-slate-500">Grouped by category on the FAQ page.</p>
        </div>
        <a href="{{ route('admin.faqs.create') }}" class="rounded-lg bg-teal-800 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-900">+ Add FAQ</a>
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="w-full min-w-[720px] text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3">Category</th>
                    <th class="px-4 py-3">Question</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($faqs as $faq)
                    <tr>
                        <td class="px-4 py-3"><span class="rounded-full bg-teal-50 px-2 py-0.5 text-xs font-medium text-teal-700">{{ $faq->category }}</span></td>
                        <td class="px-4 py-3 font-medium text-slate-800">{{ $faq->question }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('admin.faqs.edit', $faq) }}" class="font-medium text-teal-700 hover:underline">Edit</a>
                            <form action="{{ route('admin.faqs.destroy', $faq) }}" method="POST" class="inline" onsubmit="return confirm('Delete this FAQ?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="ml-3 font-medium text-red-600 hover:underline">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-4 py-10 text-center text-slate-400">No FAQs yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

</x-admin.layout>
