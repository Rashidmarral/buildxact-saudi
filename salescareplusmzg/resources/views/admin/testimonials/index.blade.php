<x-admin.layout title="Testimonials">

    <div class="mb-6 flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-bold text-slate-900">Testimonials</h2>
            <p class="mt-1 text-sm text-slate-500">Customer quotes shown across the site.</p>
        </div>
        <a href="{{ route('admin.testimonials.create') }}" class="rounded-lg bg-teal-800 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-900">+ Add Testimonial</a>
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="w-full min-w-[720px] text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3">Name</th>
                    <th class="px-4 py-3">Organization</th>
                    <th class="px-4 py-3">Quote</th>
                    <th class="px-4 py-3">Rating</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($testimonials as $testimonial)
                    <tr>
                        <td class="px-4 py-3 font-medium text-slate-800">{{ $testimonial->name }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $testimonial->organization }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ \Illuminate\Support\Str::limit($testimonial->quote, 60) }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $testimonial->rating }}/5</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('admin.testimonials.edit', $testimonial) }}" class="font-medium text-teal-700 hover:underline">Edit</a>
                            <form action="{{ route('admin.testimonials.destroy', $testimonial) }}" method="POST" class="inline" onsubmit="return confirm('Delete this testimonial?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="ml-3 font-medium text-red-600 hover:underline">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-slate-400">No testimonials yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

</x-admin.layout>
