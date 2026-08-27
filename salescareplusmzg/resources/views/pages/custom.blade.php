<x-layout :title="$page->title" :description="$page->meta_description">

    @forelse ($page->sections as $section)
        @include('pages.sections.'.$section->type, ['section' => $section])
    @empty
        <div class="mx-auto max-w-3xl px-4 py-24 text-center sm:px-6 lg:px-8">
            <h1 class="text-3xl font-bold text-teal-900">{{ $page->title }}</h1>
            <p class="mt-4 text-slate-500">This page doesn't have any content yet.</p>
        </div>
    @endforelse

</x-layout>
