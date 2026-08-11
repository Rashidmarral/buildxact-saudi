<x-admin.layout title="Product Categories">

    <div class="mb-6 flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-bold text-slate-900">Product Categories</h2>
            <p class="mt-1 text-sm text-slate-500">Groups shown on the Catalog page.</p>
        </div>
        <a href="{{ route('admin.product-categories.create') }}" class="rounded-lg bg-teal-800 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-900">+ Add Category</a>
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="w-full min-w-[640px] text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3">Name</th>
                    <th class="px-4 py-3">Icon</th>
                    <th class="px-4 py-3">Products</th>
                    <th class="px-4 py-3">Order</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($categories as $category)
                    <tr>
                        <td class="px-4 py-3 font-medium text-slate-800">{{ $category->name }}</td>
                        <td class="px-4 py-3"><x-icon :name="$category->icon" class="h-4 w-4 text-teal-700" /></td>
                        <td class="px-4 py-3 text-slate-500">{{ $category->products_count }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $category->sort_order }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('admin.product-categories.edit', $category) }}" class="font-medium text-teal-700 hover:underline">Edit</a>
                            <form action="{{ route('admin.product-categories.destroy', $category) }}" method="POST" class="inline" onsubmit="return confirm('Delete this category?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="ml-3 font-medium text-red-600 hover:underline">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-slate-400">No categories yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

</x-admin.layout>
