@php $editing = $category->exists; @endphp
<x-admin.layout :title="$editing ? 'Edit Category' : 'Add Category'">

    <a href="{{ route('admin.product-categories.index') }}" class="text-sm font-medium text-teal-700 hover:underline">&larr; Back to Categories</a>

    <form method="POST" action="{{ $editing ? route('admin.product-categories.update', $category) : route('admin.product-categories.store') }}" class="mt-4 max-w-xl space-y-5 rounded-xl border border-slate-200 bg-white p-6">
        @csrf
        @if ($editing) @method('PUT') @endif

        <x-admin.field label="Name" name="name" :value="$category->name" required />

        <x-admin.field label="Icon" name="icon" type="select" :value="$category->icon" required>
            <option value="">Select an icon</option>
            @foreach (['pill','droplet','thermometer','shield','heart','leaf','sprout','sun','wind','class','warehouse','truck'] as $icon)
                <option value="{{ $icon }}" @selected(old('icon', $category->icon) === $icon)>{{ ucfirst($icon) }}</option>
            @endforeach
        </x-admin.field>

        <x-admin.field label="Description" name="description" type="textarea" :value="$category->description" />

        <x-admin.field label="Sort Order" name="sort_order" type="number" :value="$category->sort_order ?? 0" hint="Lower numbers appear first." />

        <button type="submit" class="rounded-lg bg-teal-800 px-5 py-2.5 text-sm font-semibold text-white hover:bg-teal-900">
            {{ $editing ? 'Save Changes' : 'Create Category' }}
        </button>
    </form>

</x-admin.layout>
