<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\CmsPage;
use App\Models\CmsSection;

/**
 * Renders a page a super admin created from Admin -> Website CMS (not one
 * of the 6 built-in pages, which each keep their own dedicated route +
 * Blade view for page-specific chrome like Pricing's plan cards). This is
 * the generic fallback: title + whatever CmsSection blocks the admin
 * added, same partials as every other page.
 */
class CmsPageController extends Controller
{
    public function show(string $slug)
    {
        $page = CmsPage::query()
            ->where('slug', $slug)
            ->where('is_system', false)
            ->where('is_active', true)
            ->firstOrFail();

        return view('site.cms-page', [
            'page' => $page,
            'sections' => CmsSection::forPage($slug),
        ]);
    }
}
