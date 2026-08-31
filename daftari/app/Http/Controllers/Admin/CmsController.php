<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CmsPage;
use App\Models\CmsSection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * "Website CMS" — lets a super admin manage the content blocks (hero, stats,
 * feature grid, testimonials, FAQ, contact info, social links, CTA banner)
 * that make up each public marketing page, without touching Blade files or
 * deploying — including adding brand new pages (see App\Models\CmsPage).
 * See App\Models\CmsSection for the fixed set of block types and
 * App\Models\CmsSectionItem for a section's repeatable rows (feature cards,
 * FAQ entries, ...), which this controller syncs by delete-then-recreate on
 * every save — the same pattern ItemController uses for alt units.
 */
class CmsController extends Controller
{
    public function index()
    {
        return redirect()->route('admin.cms.pages.show', 'home');
    }

    public function show(string $page)
    {
        $cmsPage = CmsPage::query()->where('slug', $page)->firstOrFail();

        $sections = CmsSection::query()
            ->where('page', $page)
            ->orderBy('sort_order')
            ->withCount('items')
            ->get();

        return view('admin.cms.show', [
            'page' => $page,
            'cmsPage' => $cmsPage,
            'pages' => CmsPage::query()->orderBy('sort_order')->get(),
            'sections' => $sections,
            'types' => CmsSection::allowedTypesForPage($page),
        ]);
    }

    public function storePage(Request $request)
    {
        $data = $request->validate([
            'name_en' => ['required', 'string', 'max:100'],
            'name_ar' => ['nullable', 'string', 'max:100'],
            'slug' => [
                'nullable', 'string', 'max:60', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/',
                Rule::notIn(CmsPage::RESERVED_SLUGS),
                Rule::unique('cms_pages', 'slug'),
            ],
            'show_in_footer' => ['nullable', 'boolean'],
        ], [
            'slug.regex' => __('Use lowercase letters, numbers, and hyphens only (e.g. our-story).'),
        ]);

        $slug = ($data['slug'] ?? null) ?: Str::slug($data['name_en']);

        if ($slug === '' || in_array($slug, CmsPage::RESERVED_SLUGS, true) || CmsPage::query()->where('slug', $slug)->exists()) {
            return back()->withErrors(['slug' => __('That page address is already taken or reserved — try a different name or set a custom address.')])->withInput();
        }

        $maxOrder = (int) CmsPage::query()->max('sort_order');

        $cmsPage = CmsPage::create([
            'slug' => $slug,
            'name_en' => $data['name_en'],
            'name_ar' => $data['name_ar'] ?? null,
            'is_system' => false,
            'is_active' => true,
            'show_in_footer' => $request->boolean('show_in_footer'),
            'sort_order' => $maxOrder + 1,
        ]);

        AuditLog::record('cms.page.create', null, __('Created a new page: :name', ['name' => $cmsPage->name_en]));

        return redirect()->route('admin.cms.pages.show', $cmsPage->slug)->with('status', __('Page created — add sections below to build it.'));
    }

    public function updatePage(Request $request, CmsPage $cmsPage)
    {
        abort_if($cmsPage->is_system, 403, __('Built-in pages can\'t be renamed or removed.'));

        $data = $request->validate([
            'name_en' => ['required', 'string', 'max:100'],
            'name_ar' => ['nullable', 'string', 'max:100'],
            'show_in_footer' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $cmsPage->update([
            'name_en' => $data['name_en'],
            'name_ar' => $data['name_ar'] ?? null,
            'show_in_footer' => $request->boolean('show_in_footer'),
            'is_active' => $request->boolean('is_active'),
        ]);

        AuditLog::record('cms.page.update', null, __('Updated page: :name', ['name' => $cmsPage->name_en]));

        return redirect()->route('admin.cms.pages.show', $cmsPage->slug)->with('status', __('Page settings saved.'));
    }

    public function destroyPage(CmsPage $cmsPage)
    {
        abort_if($cmsPage->is_system, 403, __('Built-in pages can\'t be renamed or removed.'));

        foreach (CmsSection::query()->where('page', $cmsPage->slug)->get() as $section) {
            if ($section->image_path) {
                Storage::disk('public')->delete($section->image_path);
            }
            $section->delete();
        }

        $name = $cmsPage->name_en;
        $cmsPage->delete();

        AuditLog::record('cms.page.delete', null, __('Removed page: :name', ['name' => $name]));

        return redirect()->route('admin.cms.pages.show', 'home')->with('status', __('Page removed.'));
    }

    public function storeSection(Request $request, string $page)
    {
        $cmsPage = CmsPage::query()->where('slug', $page)->firstOrFail();

        $data = $request->validate([
            'type' => ['required', Rule::in(CmsSection::allowedTypesForPage($page))],
        ]);

        $nextOrder = (int) CmsSection::query()->where('page', $page)->max('sort_order') + 1;

        $section = CmsSection::create([
            'page' => $page,
            'type' => $data['type'],
            'sort_order' => $nextOrder,
            'is_active' => true,
        ]);

        AuditLog::record('cms.section.create', null, __('Added a :type section to the :page page', ['type' => $data['type'], 'page' => $page]));

        return redirect()->route('admin.cms.sections.edit', $section)->with('status', __('Section added — fill in its content below.'));
    }

    public function editSection(CmsSection $section)
    {
        $section->load(['items' => fn ($q) => $q->orderBy('sort_order')]);

        return view('admin.cms.edit', [
            'section' => $section,
        ]);
    }

    public function updateSection(Request $request, CmsSection $section)
    {
        $data = $request->validate([
            'is_active' => ['nullable', 'boolean'],
            'badge_en' => ['nullable', 'string', 'max:100'],
            'badge_ar' => ['nullable', 'string', 'max:100'],
            'title_en' => ['nullable', 'string', 'max:255'],
            'title_ar' => ['nullable', 'string', 'max:255'],
            'subtitle_en' => ['nullable', 'string', 'max:2000'],
            'subtitle_ar' => ['nullable', 'string', 'max:2000'],
            'body_en' => ['nullable', 'string', 'max:20000'],
            'body_ar' => ['nullable', 'string', 'max:20000'],
            'link_url' => ['nullable', 'string', 'max:255'],
            'link_text_en' => ['nullable', 'string', 'max:100'],
            'link_text_ar' => ['nullable', 'string', 'max:100'],
            'image' => ['nullable', 'image', 'max:4096'],
            'image_position' => ['nullable', Rule::in(['left', 'right'])],
            'remove_image' => ['nullable', 'boolean'],
            'items' => ['nullable', 'array'],
            'items.*.icon' => ['nullable', 'string', 'max:50'],
            'items.*.title_en' => ['nullable', 'string', 'max:255'],
            'items.*.title_ar' => ['nullable', 'string', 'max:255'],
            'items.*.subtitle_en' => ['nullable', 'string', 'max:255'],
            'items.*.subtitle_ar' => ['nullable', 'string', 'max:255'],
            'items.*.body_en' => ['nullable', 'string', 'max:5000'],
            'items.*.body_ar' => ['nullable', 'string', 'max:5000'],
            'items.*.url' => ['nullable', 'string', 'max:255'],
        ]);

        $section->fill([
            'is_active' => $request->boolean('is_active'),
            'badge_en' => $data['badge_en'] ?? null,
            'badge_ar' => $data['badge_ar'] ?? null,
            'title_en' => $data['title_en'] ?? null,
            'title_ar' => $data['title_ar'] ?? null,
            'subtitle_en' => $data['subtitle_en'] ?? null,
            'subtitle_ar' => $data['subtitle_ar'] ?? null,
            'body_en' => $data['body_en'] ?? null,
            'body_ar' => $data['body_ar'] ?? null,
            'link_url' => $data['link_url'] ?? null,
            'link_text_en' => $data['link_text_en'] ?? null,
            'link_text_ar' => $data['link_text_ar'] ?? null,
            'image_position' => $data['image_position'] ?? 'right',
        ]);

        if ($request->boolean('remove_image') && $section->image_path) {
            Storage::disk('public')->delete($section->image_path);
            $section->image_path = null;
        }

        if ($request->hasFile('image')) {
            if ($section->image_path) {
                Storage::disk('public')->delete($section->image_path);
            }
            $section->image_path = $request->file('image')->store('cms', 'public');
        }

        $section->save();

        if ($section->hasItems()) {
            $section->items()->delete();

            foreach ($data['items'] ?? [] as $i => $item) {
                if (blank($item['title_en'] ?? null) && blank($item['body_en'] ?? null) && blank($item['title_ar'] ?? null)) {
                    continue;
                }

                $section->items()->create([
                    'sort_order' => $i,
                    'is_active' => true,
                    'icon' => $item['icon'] ?? null,
                    'title_en' => $item['title_en'] ?? null,
                    'title_ar' => $item['title_ar'] ?? null,
                    'subtitle_en' => $item['subtitle_en'] ?? null,
                    'subtitle_ar' => $item['subtitle_ar'] ?? null,
                    'body_en' => $item['body_en'] ?? null,
                    'body_ar' => $item['body_ar'] ?? null,
                    'meta' => filled($item['url'] ?? null) ? ['url' => $item['url']] : null,
                ]);
            }
        }

        AuditLog::record('cms.section.update', null, __('Updated a :type section on the :page page', ['type' => $section->type, 'page' => $section->page]));

        return redirect()->route('admin.cms.sections.edit', $section)->with('status', __('Section saved.'));
    }

    public function destroySection(CmsSection $section)
    {
        $page = $section->page;

        if ($section->image_path) {
            Storage::disk('public')->delete($section->image_path);
        }

        $section->delete();

        AuditLog::record('cms.section.delete', null, __('Removed a :type section from the :page page', ['type' => $section->type, 'page' => $page]));

        return redirect()->route('admin.cms.pages.show', $page)->with('status', __('Section removed.'));
    }

    public function moveSection(Request $request, CmsSection $section)
    {
        $data = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]]);

        $neighbor = CmsSection::query()
            ->where('page', $section->page)
            ->where('sort_order', $data['direction'] === 'up' ? '<' : '>', $section->sort_order)
            ->orderBy('sort_order', $data['direction'] === 'up' ? 'desc' : 'asc')
            ->first();

        if ($neighbor) {
            [$a, $b] = [$section->sort_order, $neighbor->sort_order];
            $section->update(['sort_order' => $b]);
            $neighbor->update(['sort_order' => $a]);
        }

        return back();
    }
}
