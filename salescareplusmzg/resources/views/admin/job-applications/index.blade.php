<x-admin.layout title="Job Applications">

    <div class="mb-6">
        <h2 class="text-2xl font-bold text-slate-900">Job Applications</h2>
        <p class="mt-1 text-sm text-slate-500">Submissions from the Careers page apply form.</p>
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="w-full min-w-[720px] text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3">Applicant</th>
                    <th class="px-4 py-3">Position</th>
                    <th class="px-4 py-3">Resume</th>
                    <th class="px-4 py-3">Received</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($applications as $application)
                    <tr class="{{ $application->is_read ? '' : 'bg-teal-50/40' }}">
                        <td class="px-4 py-3">
                            <p class="font-medium text-slate-800">{{ $application->name }}</p>
                            <p class="text-xs text-slate-400">{{ $application->email }}</p>
                        </td>
                        <td class="px-4 py-3 text-slate-500">{{ $application->job_title }}</td>
                        <td class="px-4 py-3">
                            @if ($application->resume_path)
                                <a href="{{ route('admin.job-applications.resume', $application) }}" class="font-medium text-teal-700 hover:underline">Download</a>
                            @else
                                <span class="text-slate-300">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-500">{{ $application->created_at->format('M j, Y') }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('admin.job-applications.show', $application) }}" class="font-medium text-teal-700 hover:underline">View</a>
                            <form action="{{ route('admin.job-applications.destroy', $application) }}" method="POST" class="inline" onsubmit="return confirm('Delete this application?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="ml-3 font-medium text-red-600 hover:underline">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-slate-400">No applications yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $applications->links() }}</div>

</x-admin.layout>
