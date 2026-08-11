@php $editing = $testimonial->exists; @endphp
<x-admin.layout :title="$editing ? 'Edit Testimonial' : 'Add Testimonial'">

    <a href="{{ route('admin.testimonials.index') }}" class="text-sm font-medium text-teal-700 hover:underline">&larr; Back to Testimonials</a>

    <form method="POST" action="{{ $editing ? route('admin.testimonials.update', $testimonial) : route('admin.testimonials.store') }}" class="mt-4 max-w-2xl space-y-5 rounded-xl border border-slate-200 bg-white p-6">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="grid gap-5 sm:grid-cols-2">
            <x-admin.field label="Name" name="name" :value="$testimonial->name" required />
            <x-admin.field label="Role / Title" name="role" :value="$testimonial->role" />
        </div>

        <x-admin.field label="Organization" name="organization" :value="$testimonial->organization" />
        <x-admin.field label="Quote" name="quote" type="textarea" :value="$testimonial->quote" required />

        <div class="grid gap-5 sm:grid-cols-2">
            <x-admin.field label="Rating (1–5)" name="rating" type="number" min="1" max="5" :value="$testimonial->rating ?? 5" required />
            <x-admin.field label="Sort Order" name="sort_order" type="number" :value="$testimonial->sort_order ?? 0" />
        </div>

        <button type="submit" class="rounded-lg bg-teal-800 px-5 py-2.5 text-sm font-semibold text-white hover:bg-teal-900">
            {{ $editing ? 'Save Changes' : 'Create Testimonial' }}
        </button>
    </form>

</x-admin.layout>
