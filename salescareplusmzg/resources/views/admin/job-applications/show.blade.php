<x-admin.layout title="Job Application">

    <a href="{{ route('admin.job-applications.index') }}" class="text-sm font-medium text-teal-700 hover:underline">&larr; Back to Job Applications</a>

    <div class="mt-4 max-w-2xl rounded-xl border border-slate-200 bg-white p-6">
        <div class="flex items-start justify-between border-b border-slate-100 pb-4">
            <div>
                <h2 class="text-lg font-bold text-slate-900">{{ $application->name }}</h2>
                <p class="mt-1 text-sm text-slate-500">
                    Applying for <span class="font-medium text-slate-700">{{ $application->job_title }}</span>
                </p>
                <p class="mt-1 text-sm text-slate-500">
                    <a href="mailto:{{ $application->email }}" class="text-teal-700 hover:underline">{{ $application->email }}</a>
                    @if ($application->phone) &middot; {{ $application->phone }} @endif
                </p>
            </div>
            <span class="shrink-0 text-xs text-slate-400">{{ $application->created_at->format('M j, Y g:ia') }}</span>
        </div>

        @if ($application->cover_message)
            <p class="mt-4 whitespace-pre-line text-sm text-slate-700">{{ $application->cover_message }}</p>
        @else
            <p class="mt-4 text-sm text-slate-400">No cover message provided.</p>
        @endif

        <div class="mt-6 flex items-center gap-3 border-t border-slate-100 pt-4">
            @if ($application->resume_path)
                <a href="{{ route('admin.job-applications.resume', $application) }}" class="rounded-lg bg-teal-800 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-900">Download Resume</a>
            @endif
            <a href="mailto:{{ $application->email }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-teal-900 hover:bg-slate-50">Reply by Email</a>
            <form action="{{ route('admin.job-applications.destroy', $application) }}" method="POST" onsubmit="return confirm('Delete this application?')">
                @csrf @method('DELETE')
                <button type="submit" class="rounded-lg border border-red-200 px-4 py-2 text-sm font-semibold text-red-600 hover:bg-red-50">Delete</button>
            </form>
        </div>
    </div>

</x-admin.layout>
