<x-admin.layout title="Contact Messages">

    <div class="mb-6">
        <h2 class="text-2xl font-bold text-slate-900">Contact Messages</h2>
        <p class="mt-1 text-sm text-slate-500">Submissions from the website contact form.</p>
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="w-full min-w-[720px] text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3">From</th>
                    <th class="px-4 py-3">Subject</th>
                    <th class="px-4 py-3">Received</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($messages as $message)
                    <tr class="{{ $message->is_read ? '' : 'bg-teal-50/40' }}">
                        <td class="px-4 py-3">
                            <p class="font-medium text-slate-800">{{ $message->name }}</p>
                            <p class="text-xs text-slate-400">{{ $message->email }}</p>
                        </td>
                        <td class="px-4 py-3 text-slate-500">{{ $message->subject ?: '—' }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $message->created_at->format('M j, Y g:ia') }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('admin.contact-messages.show', $message) }}" class="font-medium text-teal-700 hover:underline">View</a>
                            <form action="{{ route('admin.contact-messages.destroy', $message) }}" method="POST" class="inline" onsubmit="return confirm('Delete this message?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="ml-3 font-medium text-red-600 hover:underline">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-10 text-center text-slate-400">No messages yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $messages->links() }}</div>

</x-admin.layout>
