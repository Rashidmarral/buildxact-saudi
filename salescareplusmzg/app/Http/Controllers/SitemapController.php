<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\Product;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function index(): Response
    {
        $staticRoutes = [
            'home', 'about', 'principals', 'catalog.index', 'services',
            'quality', 'gallery', 'careers', 'faq', 'contact',
        ];

        $urls = [];

        foreach ($staticRoutes as $route) {
            $urls[] = ['loc' => route($route), 'priority' => $route === 'home' ? '1.0' : '0.8'];
        }

        foreach (Product::all() as $product) {
            $urls[] = ['loc' => route('catalog.show', $product), 'priority' => '0.6', 'lastmod' => $product->updated_at];
        }

        foreach (Page::where('is_published', true)->get() as $page) {
            $urls[] = ['loc' => route('page.show', $page->slug), 'priority' => '0.7', 'lastmod' => $page->updated_at];
        }

        $xml = view('sitemap', compact('urls'))->render();

        return response($xml, 200)->header('Content-Type', 'application/xml');
    }
}
