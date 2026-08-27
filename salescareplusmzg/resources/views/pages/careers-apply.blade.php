<x-layout title="Apply Now" description="Apply for a role at Sales Care Plus MZG.">

    <section class="bg-teal-pattern py-16 sm:py-20">
        <div class="mx-auto max-w-3xl px-4 text-center sm:px-6 lg:px-8">
            <span class="hero-fade-in inline-flex items-center gap-2 rounded-full bg-white/10 px-4 py-1.5 text-sm font-medium text-coral-300 ring-1 ring-white/10">
                <x-icon name="users" class="h-4 w-4" /> Careers
            </span>
            <h1 class="hero-fade-in hero-fade-in-delay-1 mt-6 text-3xl font-bold tracking-tight text-white sm:text-4xl">
                {{ $job ? 'Apply for '.$job->title : 'Send Us Your Application' }}
            </h1>
            @if ($job && $job->subtitle)
                <p class="hero-fade-in hero-fade-in-delay-2 mt-4 text-teal-200">{{ $job->subtitle }}</p>
            @endif
        </div>
    </section>

    <section class="mx-auto max-w-2xl px-4 py-16 sm:px-6 lg:px-8">
        <a href="{{ route('careers') }}" class="text-sm font-medium text-coral-600 hover:text-coral-700">&larr; Back to Careers</a>

        <form method="POST" action="{{ route('careers.apply.store') }}" enctype="multipart/form-data" class="relative mt-6 space-y-5 rounded-2xl border border-slate-100 bg-white p-6 shadow-sm sm:p-8">
            @csrf
            <x-honeypot-fields />

            @if ($job)
                <input type="hidden" name="content_item_id" value="{{ $job->id }}">
                <input type="hidden" name="job_title" value="{{ $job->title }}">
                <div>
                    <label class="block text-sm font-medium text-slate-700">Position</label>
                    <p class="mt-1.5 rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-600">{{ $job->title }}</p>
                </div>
            @else
                <div>
                    <label for="job_title" class="block text-sm font-medium text-slate-700">Position You're Applying For <span class="text-coral-500">*</span></label>
                    <input type="text" name="job_title" id="job_title" required value="{{ old('job_title') }}" placeholder="e.g. General Application"
                        class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm transition focus:border-teal-500 focus:outline-none focus:ring-1 focus:ring-teal-500">
                    @error('job_title')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
            @endif

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="name" class="block text-sm font-medium text-slate-700">Full Name <span class="text-coral-500">*</span></label>
                    <input type="text" name="name" id="name" required value="{{ old('name') }}"
                        class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm transition focus:border-teal-500 focus:outline-none focus:ring-1 focus:ring-teal-500">
                    @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="email" class="block text-sm font-medium text-slate-700">Email Address <span class="text-coral-500">*</span></label>
                    <input type="email" name="email" id="email" required value="{{ old('email') }}"
                        class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm transition focus:border-teal-500 focus:outline-none focus:ring-1 focus:ring-teal-500">
                    @error('email')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <div>
                <label for="phone" class="block text-sm font-medium text-slate-700">Phone Number</label>
                <input type="text" name="phone" id="phone" value="{{ old('phone') }}"
                    class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm transition focus:border-teal-500 focus:outline-none focus:ring-1 focus:ring-teal-500">
                @error('phone')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="resume" class="block text-sm font-medium text-slate-700">Resume / CV</label>
                <input type="file" name="resume" id="resume" accept=".pdf,.doc,.docx"
                    class="mt-1.5 w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-teal-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-teal-700 hover:file:bg-teal-100">
                <p class="mt-1 text-xs text-slate-400">PDF, DOC or DOCX, up to 5MB.</p>
                @error('resume')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="cover_message" class="block text-sm font-medium text-slate-700">Cover Message</label>
                <textarea name="cover_message" id="cover_message" rows="5" placeholder="Tell us a bit about yourself and why you'd be a great fit..."
                    class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm transition focus:border-teal-500 focus:outline-none focus:ring-1 focus:ring-teal-500">{{ old('cover_message') }}</textarea>
                @error('cover_message')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <button type="submit" class="w-full rounded-lg bg-teal-800 px-5 py-3 text-sm font-semibold text-white transition hover:bg-teal-900">
                Submit Application
            </button>
        </form>
    </section>

</x-layout>
