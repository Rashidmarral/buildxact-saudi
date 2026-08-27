<x-admin.layout title="Newsletter Subscribers">

    <div class="mb-6">
        <h2 class="text-2xl font-bold text-slate-900">Newsletter Subscribers</h2>
        <p class="mt-1 text-sm text-slate-500">Emails collected from the footer signup form.</p>
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="w-full min-w-[480px] text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3">Email</th>
                    <th class="px-4 py-3">Subscribed</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($subscribers as $subscriber)
                    <tr>
                        <td class="px-4 py-3 font-medium text-slate-800">{{ $subscriber->email }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $subscriber->created_at->format('M j, Y') }}</td>
                        <td class="px-4 py-3 text-right">
                            <form action="{{ route('admin.newsletter-subscribers.destroy', $subscriber) }}" method="POST" onsubmit="return confirm('Remove this subscriber?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="font-medium text-red-600 hover:underline">Remove</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-4 py-10 text-center text-slate-400">No subscribers yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $subscribers->links() }}</div>

</x-admin.layout>
