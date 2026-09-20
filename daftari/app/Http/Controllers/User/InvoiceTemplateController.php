<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\InvoiceItem;
use App\Models\InvoiceTemplate;
use App\Services\ZatcaQrGenerator;
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

    private const DENSITIES = ['compact', 'comfortable'];

    private const LANGUAGE_MODES = ['bilingual', 'english_only', 'arabic_only'];

    private const TABLE_DIRECTIONS = ['ltr', 'rtl'];

    private const PAGE_SIZES = ['a4', 'letter'];

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

        $company = Auth::user()->company;

        if ($company->hasReachedPlanLimit('invoice_templates')) {
            return redirect()->route('app.invoice-templates.index')
                ->withErrors(['plan_limit' => __('You have reached your plan\'s invoice template limit. Upgrade your plan to add more templates.')]);
        }

        $preset = InvoiceTemplatePresets::find($data['preset']);
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

        $company = Auth::user()->company;

        if ($company->hasReachedPlanLimit('invoice_templates')) {
            return redirect()->route('app.invoice-templates.index')
                ->withErrors(['plan_limit' => __('You have reached your plan\'s invoice template limit. Upgrade your plan to add more templates.')]);
        }

        $template = $company->invoiceTemplates()->create($data + [
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
            'table_header_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'remove_table_header_color' => ['nullable', 'boolean'],
            'totals_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'remove_totals_color' => ['nullable', 'boolean'],
            'layout' => ['required', Rule::in(self::LAYOUTS)],
            'density' => ['nullable', Rule::in(self::DENSITIES)],
            'language_mode' => ['required', Rule::in(self::LANGUAGE_MODES)],
            'table_direction' => ['required', Rule::in(self::TABLE_DIRECTIONS)],
            'page_size' => ['nullable', Rule::in(self::PAGE_SIZES)],
            'show_signature' => ['nullable', 'boolean'],
            'signature_label_en' => ['nullable', 'string', 'max:255'],
            'signature_label_ar' => ['nullable', 'string', 'max:255'],
            'show_logo' => ['nullable', 'boolean'],
            'show_unit_labels' => ['nullable', 'boolean'],
            'show_party_vat_number' => ['nullable', 'boolean'],
            'show_item_description' => ['nullable', 'boolean'],
            'show_vat_column' => ['nullable', 'boolean'],
            'letterhead' => ['nullable', 'image', 'max:4096'],
            'footer' => ['nullable', 'image', 'max:4096'],
            'remove_footer' => ['nullable', 'boolean'],
            'watermark' => ['nullable', 'image', 'max:4096'],
            'remove_watermark' => ['nullable', 'boolean'],
            'watermark_opacity' => ['nullable', 'integer', 'between:1,100'],
            'notes_en' => ['nullable', 'string', 'max:2000'],
            'notes_ar' => ['nullable', 'string', 'max:2000'],
            'terms_en' => ['nullable', 'string', 'max:4000'],
            'terms_ar' => ['nullable', 'string', 'max:4000'],
        ]);

        $data['show_logo'] = $request->boolean('show_logo');
        $data['show_signature'] = $request->boolean('show_signature');
        $data['show_unit_labels'] = $request->boolean('show_unit_labels');
        $data['show_party_vat_number'] = $request->boolean('show_party_vat_number');
        $data['show_item_description'] = $request->boolean('show_item_description');
        $data['show_vat_column'] = $request->boolean('show_vat_column');
        $data['watermark_opacity'] = $data['watermark_opacity'] ?? $invoiceTemplate->watermark_opacity;
        $data['page_size'] = $data['page_size'] ?? $invoiceTemplate->page_size;
        $data['density'] = $data['density'] ?? $invoiceTemplate->density;
        $data['table_header_color'] = $request->boolean('remove_table_header_color') ? null : ($data['table_header_color'] ?? $invoiceTemplate->table_header_color);
        $data['totals_color'] = $request->boolean('remove_totals_color') ? null : ($data['totals_color'] ?? $invoiceTemplate->totals_color);
        unset($data['letterhead'], $data['footer'], $data['remove_footer'], $data['watermark'], $data['remove_watermark'], $data['remove_table_header_color'], $data['remove_totals_color']);

        if ($request->hasFile('letterhead')) {
            if ($invoiceTemplate->letterhead_path) {
                Storage::disk('public')->delete($invoiceTemplate->letterhead_path);
            }
            $data['letterhead_path'] = $request->file('letterhead')->store('letterheads', 'public');
        }

        if ($request->hasFile('footer')) {
            if ($invoiceTemplate->footer_path) {
                Storage::disk('public')->delete($invoiceTemplate->footer_path);
            }
            $data['footer_path'] = $request->file('footer')->store('footers', 'public');
        } elseif ($request->boolean('remove_footer') && $invoiceTemplate->footer_path) {
            Storage::disk('public')->delete($invoiceTemplate->footer_path);
            $data['footer_path'] = null;
        }

        if ($request->hasFile('watermark')) {
            if ($invoiceTemplate->watermark_path) {
                Storage::disk('public')->delete($invoiceTemplate->watermark_path);
            }
            $data['watermark_path'] = $request->file('watermark')->store('watermarks', 'public');
        } elseif ($request->boolean('remove_watermark') && $invoiceTemplate->watermark_path) {
            Storage::disk('public')->delete($invoiceTemplate->watermark_path);
            $data['watermark_path'] = null;
        }

        $invoiceTemplate->update($data);

        return back()->with('status', __('Template saved.'));
    }

    public function destroy(InvoiceTemplate $invoiceTemplate): RedirectResponse
    {
        if ($invoiceTemplate->letterhead_path) {
            Storage::disk('public')->delete($invoiceTemplate->letterhead_path);
        }
        if ($invoiceTemplate->footer_path) {
            Storage::disk('public')->delete($invoiceTemplate->footer_path);
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

    /**
     * A visual gallery of built-in layouts (plus the hardcoded "Default"
     * look) — the primary, one-click entry point most companies want;
     * the sidebar+form editor above stays reachable for anyone who needs
     * per-document-type overrides or deeper customization.
     */
    public function gallery()
    {
        $company = Auth::user()->company;

        $activePresetKey = $company->invoiceTemplates()
            ->where('document_type', 'all')
            ->where('is_default', true)
            ->value('preset_key');

        return view('user.invoice-templates.gallery', [
            'presets' => InvoiceTemplatePresets::all(),
            'activePresetKey' => $activePresetKey,
        ]);
    }

    /**
     * Renders a standalone, chrome-free page with sample data through
     * the exact same partial a real document uses (documents.print.body)
     * — a live preview, not a static screenshot, so it can never go
     * stale against a layout tweak. Embedded at a fraction of its real
     * size inside the gallery card's iframe.
     */
    public function previewPreset(string $preset)
    {
        abort_unless($preset === 'default' || InvoiceTemplatePresets::find($preset), 404);

        $company = Auth::user()->company;
        $template = $preset === 'default' ? null : new InvoiceTemplate(InvoiceTemplatePresets::find($preset));
        $doc = $this->samplePreviewDoc($company);

        return view('documents.print.preview-frame', compact('doc', 'company', 'template'));
    }

    /**
     * "Activate" makes the chosen look the one used everywhere — every
     * document type, both the in-app show page and every downloaded/
     * emailed PDF — by (re)using a single document_type='all' template
     * per preset (idempotent: clicking Activate again just updates the
     * same row instead of piling up duplicates) and clearing is_default
     * on every other template the company has, so nothing left over from
     * the advanced editor can silently keep overriding it for one type.
     */
    public function activatePreset(Request $request, string $preset): RedirectResponse
    {
        abort_unless($preset === 'default' || InvoiceTemplatePresets::find($preset), 404);

        $company = Auth::user()->company;

        if ($preset === 'default') {
            $company->invoiceTemplates()->update(['is_default' => false]);

            return redirect()->route('app.invoice-templates.gallery')->with('status', __('Reverted to the built-in default look.'));
        }

        $presetData = InvoiceTemplatePresets::find($preset);
        $existing = $company->invoiceTemplates()->where('preset_key', $preset)->first();

        if (! $existing && $company->hasReachedPlanLimit('invoice_templates')) {
            return redirect()->route('app.invoice-templates.gallery')
                ->withErrors(['plan_limit' => __('You have reached your plan\'s invoice template limit. Upgrade your plan to add more templates.')]);
        }

        $company->invoiceTemplates()->where('id', '!=', $existing?->id)->update(['is_default' => false]);

        $company->invoiceTemplates()->updateOrCreate(
            ['preset_key' => $preset],
            [
                'name' => $presetData['name'],
                'name_ar' => $presetData['name_ar'],
                'document_type' => 'all',
                'accent_color' => $presetData['accent_color'],
                'layout' => $presetData['layout'],
                'is_default' => true,
            ]
        );

        return redirect()->route('app.invoice-templates.gallery')
            ->with('status', __('":name" is now used on every document and PDF.', ['name' => $presetData['name']]));
    }

    /**
     * Realistic but entirely synthetic sample data for a preview — the
     * real company's name/logo/VAT (so the preview actually looks like
     * their documents), a placeholder client, and two placeholder lines.
     * Built from unsaved model instances rather than arrays/stdClass so
     * every accessor and relation call body.blade.php makes ($line->unit,
     * method_exists($party, 'fullAddress'), ...) resolves exactly as it
     * would for a real document, just gracefully empty where nothing was
     * set.
     */
    private function samplePreviewDoc($company): array
    {
        $client = new Client([
            'name' => 'Nolwa Private Limited',
            'name_ar' => 'نولوا المحدودة',
            'vat_number' => '300012345600003',
            'city' => 'Riyadh',
        ]);

        $lines = collect([
            new InvoiceItem(['description' => 'Product One', 'quantity' => 2, 'unit_price' => 150, 'vat_rate' => 15, 'vat_amount' => 45, 'line_total' => 345]),
            new InvoiceItem(['description' => 'Product Two', 'quantity' => 1, 'unit_price' => 500, 'vat_rate' => 15, 'vat_amount' => 75, 'line_total' => 575]),
        ]);

        $qrCode = null;

        try {
            $qrCode = ZatcaQrGenerator::generate($company->name ?: 'Sample Company', $company->vat_number ?: '300000000000003', now(), 920, 120);
        } catch (\Throwable) {
            // A demo preview never fails the page over a QR image — the
            // image tag itself already hides gracefully on a bad src.
        }

        return [
            'type_label' => __('Standard tax invoice'),
            'type_label_ar' => 'فاتورة ضريبية عادية',
            'number' => 'INV-0001',
            'date_label' => __('Issued'),
            'date' => now(),
            'date2_label' => __('Due'),
            'date2_label_ar' => 'الاستحقاق',
            'date2' => now()->addDays(30),
            'party_label' => __('Bill to'),
            'party_label_ar' => 'العميل',
            'party' => $client,
            'qr_code' => $qrCode,
            'lines' => $lines,
            'currency' => 'SAR',
            'subtotal' => 800,
            'discount_total' => 0,
            'discount_percent' => null,
            'vat_total' => 120,
            'total' => 920,
            'extra_rows' => [
                ['label' => __('Paid'), 'value' => 0],
                \App\Support\Money::balanceRow(920),
            ],
            'bank_account' => null,
            'salesperson' => null,
            'notes' => null,
        ];
    }
}
