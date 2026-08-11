@php $editing = $product->exists; @endphp
<x-admin.layout :title="$editing ? 'Edit Product' : 'Add Product'">

    <a href="{{ route('admin.products.index') }}" class="text-sm font-medium text-teal-700 hover:underline">&larr; Back to Products</a>

    <form method="POST" action="{{ $editing ? route('admin.products.update', $product) : route('admin.products.store') }}" enctype="multipart/form-data" class="mt-4 max-w-2xl space-y-5 rounded-xl border border-slate-200 bg-white p-6">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="grid gap-5 sm:grid-cols-2">
            <x-admin.field label="Product Name" name="name" :value="$product->name" required />

            <x-admin.field label="Category" name="product_category_id" type="select" required>
                <option value="">Select a category</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected(old('product_category_id', $product->product_category_id) == $category->id)>{{ $category->name }}</option>
                @endforeach
            </x-admin.field>
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <x-admin.field label="Generic Name" name="generic_name" :value="$product->generic_name" />
            <x-admin.field label="Pack Size" name="pack_size" :value="$product->pack_size" hint="e.g. 10x10 tablets" />
        </div>

        <x-admin.field label="Manufacturer" name="manufacturer" :value="$product->manufacturer" />

        <x-admin.field label="Description" name="description" type="textarea" :value="$product->description" />

        <div>
            <x-admin.field label="Product Image" name="image" type="file" accept="image/*" />
            @if ($product->image_path)
                <img src="{{ asset('storage/'.$product->image_path) }}" class="mt-2 h-16 w-16 rounded-lg border border-slate-200 object-cover" alt="">
            @endif
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <x-admin.field label="Sort Order" name="sort_order" type="number" :value="$product->sort_order ?? 0" />
            <x-admin.field label="Featured Product" name="is_featured" type="checkbox" :value="$product->is_featured" hint="Show on homepage highlights" />
        </div>

        <button type="submit" class="rounded-lg bg-teal-800 px-5 py-2.5 text-sm font-semibold text-white hover:bg-teal-900">
            {{ $editing ? 'Save Changes' : 'Create Product' }}
        </button>
    </form>

</x-admin.layout>
