<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesUploads;
use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\PageSection;
use Illuminate\Http\Request;

class PageSectionController extends Controller
{
    use HandlesUploads;

    public function create(Page $page)
    {
        return view('admin.page-sections.form', ['page' => $page, 'section' => new PageSection]);
    }

    public function store(Request $request, Page $page)
    {
        $validated = $this->validated($request);
        $validated['page_id'] = $page->id;
        $validated['image_path'] = $this->storeUpload($request, 'image', 'sections');
        $validated['video_path'] = $this->storeUpload($request, 'video', 'sections');
        $validated['items'] = $this->parseItems($request->input('items_raw'));
        $validated['sort_order'] = $validated['sort_order'] ?? ($page->sections()->max('sort_order') + 1);

        PageSection::create($validated);

        return redirect()->route('admin.pages.edit', $page)->with('status', 'Section added.');
    }

    public function edit(Page $page, PageSection $section)
    {
        return view('admin.page-sections.form', compact('page', 'section'));
    }

    public function update(Request $request, Page $page, PageSection $section)
    {
        $validated = $this->validated($request);
        $validated['image_path'] = $this->storeUpload($request, 'image', 'sections', $section->image_path);
        $validated['video_path'] = $this->storeUpload($request, 'video', 'sections', $section->video_path);
        $validated['items'] = $this->parseItems($request->input('items_raw'));

        $section->update($validated);

        return redirect()->route('admin.pages.edit', $page)->with('status', 'Section updated.');
    }

    public function destroy(Page $page, PageSection $section)
    {
        $section->delete();

        return redirect()->route('admin.pages.edit', $page)->with('status', 'Section removed.');
    }

    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'type' => ['required', 'in:'.implode(',', array_keys(PageSection::TYPES))],
            'heading' => ['nullable', 'string', 'max:200'],
            'subheading' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:5000'],
            'video_url' => ['nullable', 'string', 'max:500'],
            'button_text' => ['nullable', 'string', 'max:100'],
            'button_url' => ['nullable', 'string', 'max:255'],
            'background' => ['nullable', 'in:white,tint,dark'],
            'animation' => ['nullable', 'in:'.implode(',', array_keys(PageSection::ANIMATIONS))],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $validated['is_visible'] = $request->boolean('is_visible');
        $validated['background'] = $validated['background'] ?? 'white';
        $validated['animation'] = $validated['animation'] ?? 'fade';

        return $validated;
    }

    /**
     * Cards/gallery items are entered as simple "Heading | Text" lines in a
     * textarea and parsed into the items JSON column — no JS-heavy repeater
     * needed for an admin who just wants to paste content in.
     */
    private function parseItems(?string $raw): ?array
    {
        if (! $raw) {
            return null;
        }

        $items = [];

        foreach (explode("\n", $raw) as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            [$heading, $text] = array_pad(explode('|', $line, 2), 2, '');
            $items[] = ['heading' => trim($heading), 'text' => trim($text)];
        }

        return $items ?: null;
    }
}
