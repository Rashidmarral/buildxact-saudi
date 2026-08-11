<x-admin.layout title="Contact Message">

    <a href="{{ route('admin.contact-messages.index') }}" class="text-sm font-medium text-teal-700 hover:underline">&larr; Back to Contact Messages</a>

    <div class="mt-4 max-w-2xl rounded-xl border border-slate-200 bg-white p-6">
        <div class="flex items-start justify-between border-b border-slate-100 pb-4">
            <div>
                <h2 class="text-lg font-bold text-slate-900">{{ $message->subject ?: 'No subject' }}</h2>
                <p class="mt-1 text-sm text-slate-500">
                    From <span class="font-medium text-slate-700">{{ $message->name }}</span>
                    &lt;<a href="mailto:{{ $message->email }}" class="text-teal-700 hover:underline">{{ $message->email }}</a>&gt;
                    @if ($message->phone) &middot; {{ $message->phone }} @endif
                </p>
            </div>
            <span class="shrink-0 text-xs text-slate-400">{{ $message->created_at->format('M j, Y g:ia') }}</span>
        </div>

        <p class="mt-4 whitespace-pre-line text-sm text-slate-700">{{ $message->message }}</p>

        <div class="mt-6 flex items-center gap-3 border-t border-slate-100 pt-4">
            <a href="mailto:{{ $message->email }}" class="rounded-lg bg-teal-800 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-900">Reply by Email</a>
            <form action="{{ route('admin.contact-messages.destroy', $message) }}" method="POST" onsubmit="return confirm('Delete this message?')">
                @csrf @method('DELETE')
                <button type="submit" class="rounded-lg border border-red-200 px-4 py-2 text-sm font-semibold text-red-600 hover:bg-red-50">Delete</button>
            </form>
        </div>
    </div>

</x-admin.layout>
