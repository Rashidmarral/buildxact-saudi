<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NavItem;
use App\Models\Page;
use Illuminate\Http\Request;

class NavItemController extends Controller
{
    /**
     * Built-in routes that can be linked from the navigation, besides
     * custom Pages. Kept in sync with routes/web.php.
     */
    public const ROUTES = [
        'home' => 'Home',
        'about' => 'About',
        'principals' => 'Principals',
        'catalog.index' => 'Catalog',
        'services' => 'Services',
        'quality' => 'Quality & Certifications',
        'gallery' => 'Gallery',
        'careers' => 'Careers',
        'faq' => 'FAQs',
        'contact' => 'Contact',
    ];

    public const LOCATIONS = [
        'header' => 'Header — Main Menu',
        'header_more' => 'Header — "More" Dropdown',
        'footer_company' => 'Footer — Company Column',
        'footer_resources' => 'Footer — Resources Column',
        'footer_legal' => 'Footer — Bottom Bar (e.g. Privacy Policy, Terms)',
    ];

    public function index()
    {
        $navItems = NavItem::with('page')->orderBy('location')->orderBy('sort_order')->get()->groupBy('location');

        return view('admin.nav-items.index', compact('navItems'));
    }

    public function create()
    {
        $pages = Page::orderBy('title')->get();

        return view('admin.nav-items.form', ['navItem' => new NavItem, 'pages' => $pages]);
    }

    public function store(Request $request)
    {
        NavItem::create($this->validated($request));

        return redirect()->route('admin.nav-items.index')->with('status', 'Navigation link created.');
    }

    public function edit(NavItem $navItem)
    {
        $pages = Page::orderBy('title')->get();

        return view('admin.nav-items.form', compact('navItem', 'pages'));
    }

    public function update(Request $request, NavItem $navItem)
    {
        $navItem->update($this->validated($request));

        return redirect()->route('admin.nav-items.index')->with('status', 'Navigation link updated.');
    }

    public function destroy(NavItem $navItem)
    {
        $navItem->delete();

        return redirect()->route('admin.nav-items.index')->with('status', 'Navigation link deleted.');
    }

    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:100'],
            'location' => ['required', 'in:'.implode(',', array_keys(self::LOCATIONS))],
            'link_type' => ['required', 'in:route,page,url'],
            'route_name' => ['nullable', 'required_if:link_type,route', 'string'],
            'page_id' => ['nullable', 'required_if:link_type,page', 'exists:pages,id'],
            'url' => ['nullable', 'required_if:link_type,url', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer'],
            'is_visible' => ['nullable', 'boolean'],
            'open_in_new_tab' => ['nullable', 'boolean'],
        ]);

        $linkType = $validated['link_type'];
        unset($validated['link_type']);

        $validated['route_name'] = $linkType === 'route' ? $validated['route_name'] : null;
        $validated['page_id'] = $linkType === 'page' ? $validated['page_id'] : null;
        $validated['url'] = $linkType === 'url' ? $validated['url'] : null;
        $validated['is_visible'] = $request->boolean('is_visible');
        $validated['open_in_new_tab'] = $request->boolean('open_in_new_tab');

        return $validated;
    }
}
