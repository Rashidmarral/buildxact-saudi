<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContentItem;
use Illuminate\Http\Request;

class ContentItemController extends Controller
{
    /**
     * Every group this screen manages: key => [Label, page it feeds, icon_mode, subtitle label].
     * icon_mode: 'icon' (pick from the site icon set), 'badge' (free-text short label,
     * e.g. a step number like "01"), or 'none' (field hidden — not used by this group).
     */
    public const GROUPS = [
        'service' => ['label' => 'Services', 'page' => 'Services page — service cards', 'icon_mode' => 'icon', 'subtitle_label' => null],
        'service_process_step' => ['label' => 'How It Works Steps', 'page' => 'Services page — "How It Works"', 'icon_mode' => 'badge', 'subtitle_label' => null],
        'job_opening' => ['label' => 'Job Openings', 'page' => 'Careers page', 'icon_mode' => 'none', 'subtitle_label' => 'Type / Location (e.g. "Full-time · Muzaffargarh")'],
        'quality_standard' => ['label' => 'Quality Standards', 'page' => 'Quality page — "How We Protect Product Quality"', 'icon_mode' => 'icon', 'subtitle_label' => null],
        'about_highlight' => ['label' => 'About Page Highlights', 'page' => 'About page — story checklist', 'icon_mode' => 'none', 'subtitle_label' => null],
        'about_value' => ['label' => 'Mission / Vision / Values', 'page' => 'About page', 'icon_mode' => 'icon', 'subtitle_label' => null],
    ];

    public function groups()
    {
        $counts = ContentItem::selectRaw('`group`, count(*) as total')->groupBy('group')->pluck('total', 'group');

        return view('admin.content-items.groups', ['groups' => self::GROUPS, 'counts' => $counts]);
    }

    public function index(string $group)
    {
        $this->ensureValidGroup($group);

        $items = ContentItem::group($group)->ordered()->get();

        return view('admin.content-items.index', ['items' => $items, 'group' => $group, 'meta' => self::GROUPS[$group]]);
    }

    public function create(string $group)
    {
        $this->ensureValidGroup($group);

        return view('admin.content-items.form', ['item' => new ContentItem, 'group' => $group, 'meta' => self::GROUPS[$group]]);
    }

    public function store(Request $request, string $group)
    {
        $this->ensureValidGroup($group);

        $validated = $this->validated($request);
        $validated['group'] = $group;

        ContentItem::create($validated);

        return redirect()->route('admin.content-items.index', $group)->with('status', 'Item added.');
    }

    public function edit(string $group, ContentItem $contentItem)
    {
        $this->ensureValidGroup($group);

        return view('admin.content-items.form', ['item' => $contentItem, 'group' => $group, 'meta' => self::GROUPS[$group]]);
    }

    public function update(Request $request, string $group, ContentItem $contentItem)
    {
        $this->ensureValidGroup($group);

        $contentItem->update($this->validated($request));

        return redirect()->route('admin.content-items.index', $group)->with('status', 'Item updated.');
    }

    public function destroy(string $group, ContentItem $contentItem)
    {
        $this->ensureValidGroup($group);

        $contentItem->delete();

        return redirect()->route('admin.content-items.index', $group)->with('status', 'Item removed.');
    }

    private function ensureValidGroup(string $group): void
    {
        abort_unless(array_key_exists($group, self::GROUPS), 404);
    }

    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'icon' => ['nullable', 'string', 'max:50'],
            'title' => ['required', 'string', 'max:150'],
            'subtitle' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $validated['is_visible'] = $request->boolean('is_visible');

        return $validated;
    }
}
