@php $editing = $faq->exists; @endphp
<x-admin.layout :title="$editing ? 'Edit FAQ' : 'Add FAQ'">

    <a href="{{ route('admin.faqs.index') }}" class="text-sm font-medium text-teal-700 hover:underline">&larr; Back to FAQs</a>

    <form method="POST" action="{{ $editing ? route('admin.faqs.update', $faq) : route('admin.faqs.store') }}" class="mt-4 max-w-2xl space-y-5 rounded-xl border border-slate-200 bg-white p-6">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="grid gap-5 sm:grid-cols-2">
            <x-admin.field label="Category" name="category" :value="$faq->category" required hint="e.g. Orders, Delivery, Quality" />
            <x-admin.field label="Sort Order" name="sort_order" type="number" :value="$faq->sort_order ?? 0" />
        </div>

        <x-admin.field label="Question" name="question" :value="$faq->question" required />
        <x-admin.field label="Answer" name="answer" type="textarea" rows="5" :value="$faq->answer" required />

        <button type="submit" class="rounded-lg bg-teal-800 px-5 py-2.5 text-sm font-semibold text-white hover:bg-teal-900">
            {{ $editing ? 'Save Changes' : 'Create FAQ' }}
        </button>
    </form>

</x-admin.layout>
