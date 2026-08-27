<x-admin.layout title="Products">

    <div class="mb-6 flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-bold text-slate-900">Products</h2>
            <p class="mt-1 text-sm text-slate-500">Items shown in the Catalog.</p>
        </div>
        <a href="{{ route('admin.products.create') }}" class="rounded-lg bg-teal-800 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-900">+ Add Product</a>
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="w-full min-w-[720px] text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3">Product</th>
                    <th class="px-4 py-3">Category</th>
                    <th class="px-4 py-3">Pack Size</th>
                    <th class="px-4 py-3">Featured</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($products as $product)
                    <tr>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                @if ($product->image_path)
                                    <img src="{{ asset('storage/'.$product->image_path) }}" class="h-9 w-9 rounded-lg object-cover" alt="">
                                @else
                                    <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-teal-50"><x-icon name="pill" class="h-4 w-4 text-teal-700" /></span>
                                @endif
                                <div>
                                    <p class="font-medium text-slate-800">{{ $product->name }}</p>
                                    @if ($product->generic_name)<p class="text-xs text-slate-400">{{ $product->generic_name }}</p>@endif
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-slate-500">{{ $product->category?->name }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $product->pack_size }}</td>
                        <td class="px-4 py-3">
                            @if ($product->is_featured)
                                <span class="rounded-full bg-coral-50 px-2 py-0.5 text-xs font-semibold text-coral-600">Featured</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('admin.products.edit', $product) }}" class="font-medium text-teal-700 hover:underline">Edit</a>
                            <form action="{{ route('admin.products.destroy', $product) }}" method="POST" class="inline" onsubmit="return confirm('Delete this product?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="ml-3 font-medium text-red-600 hover:underline">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-slate-400">No products yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $products->links() }}</div>

</x-admin.layout>
