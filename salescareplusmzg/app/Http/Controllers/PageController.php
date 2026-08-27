<?php

namespace App\Http\Controllers;

use App\Models\Page;

class PageController extends Controller
{
    public function show(string $slug)
    {
        $page = Page::where('slug', $slug)->where('is_published', true)->firstOrFail();
        $page->load(['sections' => fn ($query) => $query->where('is_visible', true)]);

        return view('pages.custom', compact('page'));
    }
}
