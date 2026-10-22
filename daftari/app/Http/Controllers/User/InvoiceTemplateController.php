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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class InvoiceTemplateController extends Controller
{
    private const TYPES = [
        'all', 'invoice', 'quotation', 'proforma', 'bill', 'purchase_order',
        'receipt_voucher', 'payment_voucher', 'voucher_summary', 'client_statement',
        'project_cash_flow', 'letter', 'machinery_statement',
    ];

    private const LAYOUTS = [
        'minimal', 'bordered', 'boxed', 'bilingual_classic', 'custom_letterhead', 'quotation_offer',
        'sage_classic', 'retail_classic', 'distribution_classic',
        'bilingual_side_by_side', 'bilingual_stacked',
    ];

    private const DENSITIES = ['compact', 'comfortable'];

    private const LANGUAGE_MODES = ['bilingual', 'english_only', 'arabic_only'];

    private const TABLE_DIRECTIONS = ['ltr', 'rtl'];

    private const PAGE_SIZES = ['a4', 'letter'];

    /**
     * "i don't have option to change quotation layout to new template ...
     * user choose the layout type and then user have multiple choice
     * selection where it is applies and also user have option to
     * deselect" — each InvoiceTemplate row is still scoped to exactly one
     * document_type (Company::defaultTemplateFor() and every print path
     * are untouched), but the editor now presents "the same design
     * applied to several document types" as one thing: a $design is a
     * group of sibling rows sharing one design_group, built here by
     * grouping the company's own rows rather than a new table.
     */
    public function index(Request $request)
    {
        $company = Auth::user()->company;
        $type = $request->query('type');

        $allTemplates = $company->invoiceTemplates()
            // pos_receipt rows are a different template family entirely
            // (see ReceiptTemplateController) — its layout keys
            // ('receipt_compact', 'receipt_detailed') aren't in this
            // controller's self::LAYOUTS, so this editor's Layout <select>
            // would never offer the right option for one. Same class of
            // bug this session already found and fixed once for a missing
            // dropdown option (silent corruption on save) — simplest fix
            // here is to never let a receipt template be reachable from
            // this list/editor at all.
            ->where('document_type', '!=', 'pos_receipt')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        $designs = $allTemplates->groupBy('design_group')->map(function ($rows) {
            $primary = $rows->firstWhere('is_default', true) ?? $rows->first();

            return (object) [
                'design_group' => $primary->design_group,
                'primary' => $primary,
                'rows' => $rows->values(),
                'document_types' => $rows->pluck('document_type')->unique()->values()->all(),
            ];
        })->values();

        // The type filter narrows which designs are listed, but never
        // hides which document types a shown design applies to — a
        // design covering both Invoice and Quotation must still show
        // both chips while filtered to "Quotations", not just the one
        // that matched.
        $visibleDesigns = ($type && $type !== 'all_types')
            ? $designs->filter(fn ($d) => in_array($type, $d->document_types, true))->values()
            : $designs;

        $selectedDesign = null;
        if ($request->filled('template')) {
            $selectedRow = $allTemplates->firstWhere('id', (int) $request->query('template'));
            if ($selectedRow) {
                $selectedDesign = $designs->firstWhere('design_group', $selectedRow->design_group);
            }
        }

        return view('user.invoice-templates.index', [
            'designs' => $visibleDesigns,
            'selected' => $selectedDesign,
            'type' => $type,
            'documentTypes' => self::TYPES,
            'presets' => InvoiceTemplatePresets::all(),
            'fontFamilies' => InvoiceTemplate::FONT_FAMILIES,
            'fontSizes' => InvoiceTemplate::FONT_SIZES,
            'borderStyles' => InvoiceTemplate::BORDER_STYLES,
        ]);
    }

    public function useTemplate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'preset' => ['required', Rule::in(array_keys(InvoiceTemplatePresets::all()))],
            'document_types' => ['required', 'array', 'min:1'],
            'document_types.*' => [Rule::in(self::TYPES)],
        ]);

        $company = Auth::user()->company;
        $preset = InvoiceTemplatePresets::find($data['preset']);

        $result = $this->createDesign($company, array_unique($data['document_types']), [
            'name' => $preset['name'],
            'name_ar' => $preset['name_ar'],
            'accent_color' => $preset['accent_color'],
            'layout' => $preset['layout'],
            'language_mode' => $preset['language_mode'] ?? 'bilingual',
            'is_default' => true,
        ]);

        if (! $result) {
            return redirect()->route('app.invoice-templates.index')
                ->withErrors(['plan_limit' => __('You have reached your plan\'s invoice template limit. Upgrade your plan to add more templates.')]);
        }

        return redirect()->route('app.invoice-templates.index', ['template' => $result->id])
            ->with('status', __('Template created from ":name". Customize it below.', ['name' => $preset['name']]));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'document_types' => ['required', 'array', 'min:1'],
            'document_types.*' => [Rule::in(self::TYPES)],
        ]);

        $company = Auth::user()->company;

        $result = $this->createDesign($company, array_unique($data['document_types']), [
            'name' => $data['name'],
            'accent_color' => '#0f766e',
            'layout' => 'minimal',
            'is_default' => true,
        ]);

        if (! $result) {
            return redirect()->route('app.invoice-templates.index')
                ->withErrors(['plan_limit' => __('You have reached your plan\'s invoice template limit. Upgrade your plan to add more templates.')]);
        }

        return redirect()->route('app.invoice-templates.index', ['template' => $result->id])
            ->with('status', __('Template created.'));
    }

    /**
     * One row per selected document type, all sharing one new
     * design_group, each replacing whatever was the active template for
     * that type — "applies to" means "active for" in this editor, so
     * checking a box and saving is the whole action; there's no separate
     * inert "created but not applied yet" state to also know about.
     * Returns null (nothing created, transaction rolled back) if the
     * plan's template limit would be exceeded partway through.
     */
    private function createDesign($company, array $types, array $attributes): ?InvoiceTemplate
    {
        $designGroup = (string) Str::uuid();

        try {
            return DB::transaction(function () use ($company, $types, $attributes, $designGroup) {
                $first = null;

                foreach ($types as $type) {
                    if ($company->hasReachedPlanLimit('invoice_templates')) {
                        throw new \RuntimeException('plan_limit');
                    }

                    $company->invoiceTemplates()->where('document_type', $type)->update(['is_default' => false]);

                    $row = $company->invoiceTemplates()->create($attributes + [
                        'document_type' => $type,
                        'design_group' => $designGroup,
                    ]);

                    $first ??= $row;
                }

                return $first;
            });
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'plan_limit') {
                return null;
            }

            throw $e;
        }
    }

    public function update(Request $request, InvoiceTemplate $invoiceTemplate): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'document_types' => ['required', 'array', 'min:1'],
            'document_types.*' => [Rule::in(self::TYPES)],
            'accent_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'table_header_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'remove_table_header_color' => ['nullable', 'boolean'],
            'totals_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'remove_totals_color' => ['nullable', 'boolean'],
            'layout' => ['required', Rule::in(self::LAYOUTS)],
            'font_family' => ['nullable', Rule::in(InvoiceTemplate::FONT_FAMILIES)],
            'font_size' => ['nullable', Rule::in(InvoiceTemplate::FONT_SIZES)],
            'border_style' => ['nullable', Rule::in(InvoiceTemplate::BORDER_STYLES)],
            'density' => ['nullable', Rule::in(self::DENSITIES)],
            'language_mode' => ['required', Rule::in(self::LANGUAGE_MODES)],
            'table_direction' => ['required', Rule::in(self::TABLE_DIRECTIONS)],
            'page_size' => ['nullable', Rule::in(self::PAGE_SIZES)],
            'show_signature' => ['nullable', 'boolean'],
            'signature_label_en' => ['nullable', 'string', 'max:255'],
            'signature_label_ar' => ['nullable', 'string', 'max:255'],
            'show_greeting' => ['nullable', 'boolean'],
            'greeting_en' => ['nullable', 'string', 'max:1000'],
            'greeting_ar' => ['nullable', 'string', 'max:1000'],
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
        $data['show_greeting'] = $request->boolean('show_greeting');
        $data['show_unit_labels'] = $request->boolean('show_unit_labels');
        $data['show_party_vat_number'] = $request->boolean('show_party_vat_number');
        $data['show_item_description'] = $request->boolean('show_item_description');
        $data['show_vat_column'] = $request->boolean('show_vat_column');
        $data['watermark_opacity'] = $data['watermark_opacity'] ?? $invoiceTemplate->watermark_opacity;
        $data['page_size'] = $data['page_size'] ?? $invoiceTemplate->page_size;
        $data['density'] = $data['density'] ?? $invoiceTemplate->density;
        $data['table_header_color'] = $request->boolean('remove_table_header_color') ? null : ($data['table_header_color'] ?? $invoiceTemplate->table_header_color);
        $data['totals_color'] = $request->boolean('remove_totals_color') ? null : ($data['totals_color'] ?? $invoiceTemplate->totals_color);

        $types = array_values(array_unique($data['document_types']));
        unset(
            $data['document_types'], $data['letterhead'], $data['footer'], $data['remove_footer'],
            $data['watermark'], $data['remove_watermark'], $data['remove_table_header_color'], $data['remove_totals_color']
        );

        // Uploaded once, shared by every row in the group (same stored
        // file, same path string) — not re-uploaded per document type.
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

        $error = $this->syncDesignGroup($invoiceTemplate, $types, $data);

        if ($error) {
            return back()->withErrors(['plan_limit' => $error])->withInput();
        }

        return back()->with('status', __('Template saved.'));
    }

    /**
     * Reconciles a design's rows against the newly submitted "Applies
     * to" list: removes a row for every deselected type (the fallback
     * template for that type takes over immediately — the company-wide
     * "all" default, or the hardcoded built-in look if there isn't one),
     * updates every still-selected type's row with the shared style
     * attributes, and creates a new row — stealing that type away from
     * whichever other design currently has it — for every newly
     * selected type. Returns a user-facing error string, or null on
     * success.
     */
    private function syncDesignGroup(InvoiceTemplate $invoiceTemplate, array $types, array $attributes): ?string
    {
        $company = Auth::user()->company;
        $groupId = $invoiceTemplate->design_group;

        $existingRows = $company->invoiceTemplates()->where('design_group', $groupId)->get()->keyBy('document_type');

        // Net row count after this save = current total - rows being
        // removed (deselected types) + rows being added (newly selected
        // types) — only the newly added ones can push the company over
        // its plan's template limit.
        $newTypesCount = collect($types)->reject(fn ($type) => $existingRows->has($type))->count();
        $removedCount = $existingRows->keys()->diff($types)->count();
        $netNewRows = $newTypesCount - $removedCount;

        if ($netNewRows > 0) {
            $projectedTotal = $company->invoiceTemplates()->count() + $netNewRows;
            $subscription = $company->activeSubscription();
            $limit = $subscription?->plan?->max_invoice_templates;

            if ($limit !== null && $projectedTotal > $limit) {
                return __('You have reached your plan\'s invoice template limit. Upgrade your plan to add more templates.');
            }
        }

        DB::transaction(function () use ($company, $groupId, $types, $attributes, $existingRows) {
            foreach ($existingRows as $type => $row) {
                if (! in_array($type, $types, true)) {
                    $row->delete();
                }
            }

            foreach ($types as $type) {
                if ($existingRows->has($type)) {
                    $existingRows[$type]->update($attributes);

                    continue;
                }

                $company->invoiceTemplates()->where('document_type', $type)->update(['is_default' => false]);
                $company->invoiceTemplates()->create($attributes + [
                    'document_type' => $type,
                    'design_group' => $groupId,
                    'is_default' => true,
                ]);
            }
        });

        return null;
    }

    public function destroy(InvoiceTemplate $invoiceTemplate): RedirectResponse
    {
        $company = Auth::user()->company;
        $siblings = $company->invoiceTemplates()->where('design_group', $invoiceTemplate->design_group)->get();

        if ($invoiceTemplate->letterhead_path) {
            Storage::disk('public')->delete($invoiceTemplate->letterhead_path);
        }
        if ($invoiceTemplate->footer_path) {
            Storage::disk('public')->delete($invoiceTemplate->footer_path);
        }
        if ($invoiceTemplate->watermark_path) {
            Storage::disk('public')->delete($invoiceTemplate->watermark_path);
        }

        foreach ($siblings as $sibling) {
            $sibling->delete();
        }

        return redirect()->route('app.invoice-templates.index')->with('status', __('Template deleted.'));
    }

    /**
     * Reactivates this design for every document type it's assigned to
     * at once — the "Applies to" checkboxes on the editor's own form
     * already do this the moment a type is checked and saved; this
     * button covers the other case, where this design already has a
     * row for a type but isn't currently the active one there (some
     * other design is), without having to open the editor and re-save.
     */
    public function makeDefault(InvoiceTemplate $invoiceTemplate): RedirectResponse
    {
        $company = Auth::user()->company;
        $siblings = $company->invoiceTemplates()->where('design_group', $invoiceTemplate->design_group)->get();

        foreach ($siblings as $sibling) {
            $company->invoiceTemplates()->where('document_type', $sibling->document_type)->update(['is_default' => false]);
            $sibling->update(['is_default' => true]);
        }

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
     * "Activate" makes the chosen look the one used everywhere that
     * doesn't have its own override — every document type, both the
     * in-app show page and every downloaded/emailed PDF — by (re)using a
     * single document_type='all' template per preset (idempotent:
     * clicking Activate again just updates the same row instead of piling
     * up duplicates) and clearing is_default on the company's other
     * document_type='all' templates only. It deliberately leaves
     * type-specific templates (document_type='invoice', 'quotation', ...)
     * alone: those are the advanced editor's per-document-type overrides,
     * set up on purpose (Company::defaultTemplateFor() already prefers
     * them over the 'all' default), and a gallery click for "everything
     * else" must not silently wipe them out from under the user.
     */
    public function activatePreset(Request $request, string $preset): RedirectResponse
    {
        abort_unless($preset === 'default' || InvoiceTemplatePresets::find($preset), 404);

        $company = Auth::user()->company;

        if ($preset === 'default') {
            $company->invoiceTemplates()->where('document_type', 'all')->update(['is_default' => false]);

            return redirect()->route('app.invoice-templates.gallery')->with('status', __('Reverted to the built-in default look.'));
        }

        $presetData = InvoiceTemplatePresets::find($preset);
        $existing = $company->invoiceTemplates()->where('preset_key', $preset)->first();

        if (! $existing && $company->hasReachedPlanLimit('invoice_templates')) {
            return redirect()->route('app.invoice-templates.gallery')
                ->withErrors(['plan_limit' => __('You have reached your plan\'s invoice template limit. Upgrade your plan to add more templates.')]);
        }

        $company->invoiceTemplates()->where('document_type', 'all')->where('id', '!=', $existing?->id)->update(['is_default' => false]);

        $company->invoiceTemplates()->updateOrCreate(
            ['preset_key' => $preset],
            [
                'name' => $presetData['name'],
                'name_ar' => $presetData['name_ar'],
                'document_type' => 'all',
                'accent_color' => $presetData['accent_color'],
                'layout' => $presetData['layout'],
                'language_mode' => $presetData['language_mode'] ?? 'bilingual',
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
            'cr_number' => '1010123456',
            'building_number' => '1234',
            'street_name' => 'King Fahd Road',
            'district' => 'Al Olaya',
            'postal_code' => '12345',
            'city' => 'Riyadh',
        ]);

        $lines = collect([
            (new InvoiceItem(['description' => 'Product One', 'quantity' => 2, 'unit_price' => 150, 'vat_rate' => 15, 'vat_amount' => 45, 'line_total' => 345]))->setRelation('item', new \App\Models\Item(['sku' => 'SKU-001'])),
            (new InvoiceItem(['description' => 'Product Two', 'quantity' => 1, 'unit_price' => 500, 'vat_rate' => 15, 'vat_amount' => 75, 'line_total' => 575]))->setRelation('item', new \App\Models\Item(['sku' => 'SKU-002'])),
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
            'payment_method' => __('Cash'),
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
