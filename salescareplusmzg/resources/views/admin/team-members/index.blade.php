<x-admin.layout title="Team Members">

    <div class="mb-6 flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-bold text-slate-900">Team Members</h2>
            <p class="mt-1 text-sm text-slate-500">Leadership shown on the About page.</p>
        </div>
        <a href="{{ route('admin.team-members.create') }}" class="rounded-lg bg-teal-800 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-900">+ Add Team Member</a>
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="w-full min-w-[640px] text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3">Name</th>
                    <th class="px-4 py-3">Designation</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($teamMembers as $member)
                    <tr>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                @if ($member->photo_path)
                                    <img src="{{ asset('storage/'.$member->photo_path) }}" class="h-9 w-9 rounded-full object-cover" alt="">
                                @else
                                    <span class="flex h-9 w-9 items-center justify-center rounded-full bg-teal-50 text-xs font-bold text-teal-800">{{ $member->initials }}</span>
                                @endif
                                <span class="font-medium text-slate-800">{{ $member->name }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-slate-500">{{ $member->designation }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('admin.team-members.edit', $member) }}" class="font-medium text-teal-700 hover:underline">Edit</a>
                            <form action="{{ route('admin.team-members.destroy', $member) }}" method="POST" class="inline" onsubmit="return confirm('Delete this team member?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="ml-3 font-medium text-red-600 hover:underline">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-4 py-10 text-center text-slate-400">No team members yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

</x-admin.layout>
