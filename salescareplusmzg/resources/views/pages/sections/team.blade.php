@php
    $members = \App\Models\TeamMember::orderBy('sort_order')->get();
    $isDark = $section->background === 'dark';
    $bgClass = match ($section->background) {
        'dark' => 'bg-teal-950 text-white',
        'tint' => 'bg-teal-50 text-teal-950',
        default => 'bg-white text-slate-700',
    };
@endphp
@if ($members->isNotEmpty())
    <section class="{{ $bgClass }} {{ $section->animationClass() }}">
        <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
            @if ($section->heading || $section->subheading)
                <div class="mx-auto mb-10 max-w-2xl text-center">
                    @if ($section->subheading)
                        <p class="mb-2 text-sm font-semibold uppercase tracking-wide {{ $isDark ? 'text-coral-300' : 'text-coral-600' }}">{{ $section->subheading }}</p>
                    @endif
                    @if ($section->heading)
                        <h2 class="text-2xl font-bold sm:text-3xl {{ $isDark ? 'text-white' : 'text-teal-900' }}">{{ $section->heading }}</h2>
                    @endif
                </div>
            @endif

            <div class="grid grid-cols-1 gap-6 reveal-stagger sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($members as $member)
                    <div class="rounded-2xl border {{ $isDark ? 'border-teal-800 bg-teal-900/60' : 'border-slate-100 bg-white shadow-sm' }} p-6 text-center transition duration-300 hover:-translate-y-1">
                        @if ($member->photo_path)
                            <img src="{{ asset('storage/'.$member->photo_path) }}" alt="{{ $member->name }}" class="mx-auto h-16 w-16 rounded-full object-cover">
                        @else
                            <span class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-coral-500 text-lg font-bold text-white">
                                {{ $member->initials }}
                            </span>
                        @endif
                        <h3 class="mt-4 font-semibold {{ $isDark ? 'text-white' : 'text-teal-900' }}">{{ $member->name }}</h3>
                        <p class="text-sm text-coral-500">{{ $member->designation }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endif
