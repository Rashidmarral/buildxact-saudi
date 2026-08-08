<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $categories = ProductCategory::withCount('products')->orderBy('sort_order')->get();

        $query = Product::with('category')->orderBy('sort_order');

        if ($request->filled('category')) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $request->string('category')));
        }

        if ($request->filled('q')) {
            $search = $request->string('q');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('generic_name', 'like', "%{$search}%");
            });
        }

        $products = $query->paginate(12)->withQueryString();

        return view('pages.products.index', compact('categories', 'products'));
    }

    public function show(Product $product)
    {
        $product->load('category');
        $related = Product::where('product_category_id', $product->product_category_id)
            ->where('id', '!=', $product->id)
            ->limit(4)
            ->get();

        return view('pages.products.show', compact('product', 'related'));
    }
}
