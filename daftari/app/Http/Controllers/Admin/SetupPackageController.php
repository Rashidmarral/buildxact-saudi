<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\SetupPackage;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Super-Admin management of "Done-For-You" setup packages (item 9).
 * Nothing is seeded, no price is hard-coded — the operator defines every
 * package and its price (or leaves it blank for "contact us for
 * pricing") here before it appears on the public /setup-packages page.
 */
class SetupPackageController extends Controller
{
    private const AUDITED_FIELDS = ['name_en', 'name_ar', 'slug', 'description_en', 'description_ar', 'price', 'is_active'];

    public function index()
    {
        $setupPackages = SetupPackage::orderBy('sort_order')->orderBy('name_en')->get();

        return view('admin.setup-packages.index', compact('setupPackages'));
    }

    public function create()
    {
        return view('admin.setup-packages.form', ['setupPackage' => new SetupPackage]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $setupPackage = SetupPackage::create($data);

        AuditLog::record('setup_package.create', null, __('Created setup package :name', ['name' => $setupPackage->name_en]), new: $setupPackage->only(self::AUDITED_FIELDS));

        return redirect()->route('admin.setup-packages.index')->with('status', __('Setup package created.'));
    }

    public function edit(SetupPackage $setupPackage)
    {
        return view('admin.setup-packages.form', compact('setupPackage'));
    }

    public function update(Request $request, SetupPackage $setupPackage)
    {
        $old = $setupPackage->only(self::AUDITED_FIELDS);

        $data = $this->validated($request, $setupPackage);
        $setupPackage->update($data);

        AuditLog::record('setup_package.update', null, __('Updated setup package :name', ['name' => $setupPackage->name_en]), old: $old, new: $setupPackage->only(self::AUDITED_FIELDS));

        return redirect()->route('admin.setup-packages.index')->with('status', __('Setup package updated.'));
    }

    public function destroy(SetupPackage $setupPackage)
    {
        if ($setupPackage->requests()->exists()) {
            return back()->withErrors(['setup_package' => __('This package has at least one request on file and cannot be deleted. Deactivate it instead.')]);
        }

        $old = $setupPackage->only(self::AUDITED_FIELDS);
        $setupPackage->delete();

        AuditLog::record('setup_package.delete', null, __('Deleted setup package :name', ['name' => $old['name_en']]), old: $old);

        return redirect()->route('admin.setup-packages.index')->with('status', __('Setup package deleted.'));
    }

    private function validated(Request $request, ?SetupPackage $setupPackage = null): array
    {
        $data = $request->validate([
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:60', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/', Rule::unique('setup_packages', 'slug')->ignore($setupPackage)],
            'description_en' => ['nullable', 'string', 'max:2000'],
            'description_ar' => ['nullable', 'string', 'max:2000'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'features_en' => ['nullable', 'string'],
            'features_ar' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ], [
            'slug.regex' => __('Use lowercase letters, numbers, and hyphens only.'),
        ]);

        $data['slug'] = ($data['slug'] ?? null) ?: Str::slug($data['name_en']);
        $data['features_en'] = $this->linesToArray($data['features_en'] ?? null);
        $data['features_ar'] = $this->linesToArray($data['features_ar'] ?? null);
        $data['is_active'] = $request->boolean('is_active', true);
        $data['sort_order'] = $data['sort_order'] ?? 0;

        return $data;
    }

    private function linesToArray(?string $text): ?array
    {
        if (! $text) {
            return null;
        }

        $lines = array_filter(array_map('trim', explode("\n", $text)), fn ($line) => $line !== '');

        return array_values($lines) ?: null;
    }
}
