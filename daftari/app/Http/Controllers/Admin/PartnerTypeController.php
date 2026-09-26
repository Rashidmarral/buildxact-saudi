<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Partner;
use App\Models\PartnerType;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Super-Admin management of partner categories and their commission
 * rules (item 12: "configurable partner types & commission rules"). No
 * type is seeded and no commission rate defaults anywhere in code — the
 * operator defines every rate here before the partner program can be
 * used at all.
 */
class PartnerTypeController extends Controller
{
    private const AUDITED_FIELDS = ['name_en', 'name_ar', 'slug', 'commission_type', 'commission_value', 'is_recurring', 'is_active'];

    public function index()
    {
        $partnerTypes = PartnerType::orderBy('sort_order')->orderBy('name_en')->get();

        return view('admin.partner-types.index', compact('partnerTypes'));
    }

    public function create()
    {
        return view('admin.partner-types.form', ['partnerType' => new PartnerType]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $partnerType = PartnerType::create($data);

        AuditLog::record('partner_type.create', null, __('Created partner type :name', ['name' => $partnerType->name_en]), new: $partnerType->only(self::AUDITED_FIELDS));

        return redirect()->route('admin.partner-types.index')->with('status', __('Partner type created.'));
    }

    public function edit(PartnerType $partnerType)
    {
        return view('admin.partner-types.form', compact('partnerType'));
    }

    public function update(Request $request, PartnerType $partnerType)
    {
        $old = $partnerType->only(self::AUDITED_FIELDS);

        $data = $this->validated($request, $partnerType);
        $partnerType->update($data);

        AuditLog::record('partner_type.update', null, __('Updated partner type :name', ['name' => $partnerType->name_en]), old: $old, new: $partnerType->only(self::AUDITED_FIELDS));

        return redirect()->route('admin.partner-types.index')->with('status', __('Partner type updated.'));
    }

    public function destroy(PartnerType $partnerType)
    {
        if (Partner::where('partner_type_id', $partnerType->id)->exists()) {
            return back()->withErrors(['partner_type' => __('This partner type is assigned to at least one partner and cannot be deleted. Deactivate it instead.')]);
        }

        $old = $partnerType->only(self::AUDITED_FIELDS);
        $partnerType->delete();

        AuditLog::record('partner_type.delete', null, __('Deleted partner type :name', ['name' => $old['name_en']]), old: $old);

        return redirect()->route('admin.partner-types.index')->with('status', __('Partner type deleted.'));
    }

    private function validated(Request $request, ?PartnerType $partnerType = null): array
    {
        $data = $request->validate([
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:60', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/', Rule::unique('partner_types', 'slug')->ignore($partnerType)],
            'commission_type' => ['required', Rule::in(PartnerType::COMMISSION_TYPES)],
            'commission_value' => ['required', 'numeric', 'min:0'],
            'is_recurring' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ], [
            'slug.regex' => __('Use lowercase letters, numbers, and hyphens only.'),
        ]);

        $data['slug'] = ($data['slug'] ?? null) ?: Str::slug($data['name_en']);
        $data['is_recurring'] = $request->boolean('is_recurring');
        $data['is_active'] = $request->boolean('is_active', true);
        $data['sort_order'] = $data['sort_order'] ?? 0;

        return $data;
    }
}
