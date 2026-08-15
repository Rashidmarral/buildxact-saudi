<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\InvoiceTemplate;
use App\Support\InvoiceTemplatePresets;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class InvoiceTemplateController extends Controller
{
    private const TYPES = [
        'all', 'invoice', 'quotation', 'proforma', 'bill', 'purchase_order',
        'receipt_voucher', 'payment_voucher',
    ];

    private const LAYOUTS = ['minimal', 'bordered', 'boxed', 'bilingual_classic', 'custom_letterhead'];

    public function index(Request $request)
    {
        $company = Auth::user()->company;
        $type = $request->query('type');

        $templates = $company->invoiceTemplates()
            ->when($type && $type !== 'all_types', fn ($q) => $q->where('document_type', $type))
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        $selected = null;
        if ($request->filled('template')) {
            $selected = $templates->firstWhere('id', (int) $request->query('template'));
        }

        return view('user.invoice-templates.index', [
            'templates' => $templates,
            'selected' => $selected,
            'type' => $type,
            'documentTypes' => self::TYPES,
            'presets' => InvoiceTemplatePresets::all(),
        ]);
    }

    public function useTemplate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'preset' => ['required', Rule::in(array_keys(InvoiceTemplatePresets::all()))],
            'document_type' => ['required', Rule::in(self::TYPES)],
        ]);

        $preset = InvoiceTemplatePresets::find($data['preset']);
        $company = Auth::user()->company;
        $isFirst = $company->invoiceTemplates()->count() === 0;

        $template = $company->invoiceTemplates()->create([
            'name' => $preset['name'],
            'name_ar' => $preset['name_ar'],
            'document_type' => $data['document_type'],
            'accent_color' => $preset['accent_color'],
            'layout' => $preset['layout'],
            'is_default' => $isFirst,
        ]);

        return redirect()->route('app.invoice-templates.index', ['template' => $template->id])
            ->with('status', __('Template created from ":name". Customize it below.', ['name' => $preset['name']]));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'document_type' => ['required', Rule::in(self::TYPES)],
        ]);

        $template = Auth::user()->company->invoiceTemplates()->create($data + [
            'accent_color' => '#0f766e',
            'layout' => 'minimal',
        ]);

        return redirect()->route('app.invoice-templates.index', ['template' => $template->id])
            ->with('status', __('Template created.'));
    }

    public function update(Request $request, InvoiceTemplate $invoiceTemplate): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'document_type' => ['required', Rule::in(self::TYPES)],
            'accent_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'layout' => ['required', Rule::in(self::LAYOUTS)],
            'show_logo' => ['nullable', 'boolean'],
            'letterhead' => ['nullable', 'image', 'max:4096'],
            'notes_en' => ['nullable', 'string', 'max:2000'],
            'notes_ar' => ['nullable', 'string', 'max:2000'],
        ]);

        $data['show_logo'] = $request->boolean('show_logo');
        unset($data['letterhead']);

        if ($request->hasFile('letterhead')) {
            if ($invoiceTemplate->letterhead_path) {
                Storage::disk('public')->delete($invoiceTemplate->letterhead_path);
            }
            $data['letterhead_path'] = $request->file('letterhead')->store('letterheads', 'public');
        }

        $invoiceTemplate->update($data);

        return back()->with('status', __('Template saved.'));
    }

    public function destroy(InvoiceTemplate $invoiceTemplate): RedirectResponse
    {
        if ($invoiceTemplate->letterhead_path) {
            Storage::disk('public')->delete($invoiceTemplate->letterhead_path);
        }

        $invoiceTemplate->delete();

        return redirect()->route('app.invoice-templates.index')->with('status', __('Template deleted.'));
    }

    public function makeDefault(InvoiceTemplate $invoiceTemplate): RedirectResponse
    {
        Auth::user()->company->invoiceTemplates()
            ->where('document_type', $invoiceTemplate->document_type)
            ->update(['is_default' => false]);

        $invoiceTemplate->update(['is_default' => true]);

        return back()->with('status', __('Set as the default template.'));
    }
}
