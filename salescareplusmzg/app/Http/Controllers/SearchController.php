<?php

namespace App\Http\Controllers;

use App\Models\Faq;
use App\Models\Page;
use App\Models\Principal;
use App\Models\Product;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function index(Request $request)
    {
        $query = trim((string) $request->query('q'));

        $results = [
            'products' => collect(),
            'principals' => collect(),
            'pages' => collect(),
            'faqs' => collect(),
        ];

        if ($query !== '') {
            $results['products'] = Product::with('category')
                ->where(function ($q) use ($query) {
                    $q->where('name', 'like', "%{$query}%")
                        ->orWhere('generic_name', 'like', "%{$query}%")
                        ->orWhere('description', 'like', "%{$query}%");
                })
                ->limit(10)->get();

            $results['principals'] = Principal::where('name', 'like', "%{$query}%")
                ->orWhere('tagline', 'like', "%{$query}%")
                ->orWhere('description', 'like', "%{$query}%")
                ->limit(10)->get();

            $results['pages'] = Page::where('is_published', true)
                ->where(function ($q) use ($query) {
                    $q->where('title', 'like', "%{$query}%")
                        ->orWhere('meta_description', 'like', "%{$query}%");
                })
                ->limit(10)->get();

            $results['faqs'] = Faq::where('question', 'like', "%{$query}%")
                ->orWhere('answer', 'like', "%{$query}%")
                ->limit(10)->get();
        }

        $total = $results['products']->count() + $results['principals']->count()
            + $results['pages']->count() + $results['faqs']->count();

        return view('pages.search', ['query' => $query, 'results' => $results, 'total' => $total]);
    }
}
