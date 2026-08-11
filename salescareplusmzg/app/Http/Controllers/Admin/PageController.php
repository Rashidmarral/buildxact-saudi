<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PageController extends Controller
{
    public function index()
    {
        $pages = Page::withCount('sections')->orderBy('sort_order')->get();

        return view('admin.pages.index', compact('pages'));
    }

    public function create()
    {
        return view('admin.pages.form', ['page' => new Page]);
    }

    public function store(Request $request)
    {
        $validated = $this->validated($request);
        $validated['slug'] = $validated['slug'] ? Str::slug($validated['slug']) : Str::slug($request->title);

        $page = Page::create($validated);

        return redirect()->route('admin.pages.edit', $page)->with('status', 'Page created. Now add some sections below.');
    }

    public function edit(Page $page)
    {
        $page->load('sections');

        return view('admin.pages.form', compact('page'));
    }

    public function update(Request $request, Page $page)
    {
        $validated = $this->validated($request);
        $validated['slug'] = $validated['slug'] ? Str::slug($validated['slug']) : Str::slug($request->title);

        $page->update($validated);

        return redirect()->route('admin.pages.edit', $page)->with('status', 'Page updated.');
    }

    public function destroy(Page $page)
    {
        $page->delete();

        return redirect()->route('admin.pages.index')->with('status', 'Page deleted.');
    }

    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'slug' => ['nullable', 'string', 'max:150', 'alpha_dash'],
            'meta_description' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $validated['is_published'] = $request->boolean('is_published');

        return $validated;
    }
}
