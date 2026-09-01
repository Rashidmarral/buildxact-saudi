<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Controllers\User\Concerns\ResolvesPerPage;
use App\Http\Controllers\User\Concerns\ExportsCsv;
use App\Http\Controllers\User\Concerns\ImportsCsv;
use App\Models\AuditLog;
use App\Models\BillItem;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\ItemUnit;
use App\Models\PurchaseOrderItem;
use App\Models\QuotationItem;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Services\Limits\UsageLimitService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ItemController extends Controller
{
    use ExportsCsv, ImportsCsv, ResolvesPerPage;

    public function index(Request $request)
    {
        $query = Item::orderBy('name');

        if ($request->query('export') === 'csv') {
            return $this->csvResponse('items.csv', $this->csvHeader(), $query->get()->map(fn ($item) => $this->csvRow($item)));
        }

        $items = $query->paginate($this->resolvePerPage($request))->withQueryString();

        return view('user.items.index', compact('items'));
    }

    /**
     * Audit finding MEDIUM-15: no list page could act on more than one
     * record at a time. Exports exactly the checked rows, independent of
     * the current filter.
     */
    public function bulkExport(Request $request)
    {
        $ids = $this->validatedBulkIds($request);
        $items = Item::whereIn('id', $ids)->orderBy('name')->get();

        return $this->csvResponse('items-selected.csv', $this->csvHeader(), $items->map(fn ($item) => $this->csvRow($item)));
    }

    /**
     * Unlike the single-row destroy() below (which deletes unconditionally,
     * with no check at all), bulk delete adds the guard that was missing:
     * an item already used on an invoice, quotation, bill, or purchase
     * order line is kept rather than orphaning that line's item reference.
     */
    public function bulkDestroy(Request $request)
    {
        $ids = $this->validatedBulkIds($request);
        $items = Item::whereIn('id', $ids)->get();

        $deleted = 0;

        foreach ($items as $item) {
            $isUsed = InvoiceItem::where('item_id', $item->id)->exists()
                || QuotationItem::where('item_id', $item->id)->exists()
                || BillItem::where('item_id', $item->id)->exists()
                || PurchaseOrderItem::where('item_id', $item->id)->exists();

            if ($isUsed) {
                continue;
            }

            if ($item->image_path) {
                Storage::disk('public')->delete($item->image_path);
            }

            $item->delete();
            $deleted++;
        }

        $skipped = $items->count() - $deleted;

        return back()->with('status', $skipped > 0
            ? __(':deleted deleted, :skipped skipped — items already used on a document cannot be bulk deleted.', ['deleted' => $deleted, 'skipped' => $skipped])
            : __(':deleted item(s) deleted.', ['deleted' => $deleted]));
    }

    private function validatedBulkIds(Request $request): array
    {
        return $request->validate(['ids' => ['required', 'array', 'min:1'], 'ids.*' => ['integer']])['ids'];
    }

    private function csvHeader(): array
    {
        return [__('Name'), __('SKU'), __('Type'), __('Unit price'), __('VAT rate'), __('Status')];
    }

    private function csvRow(Item $item): array
    {
        return [
            $item->name,
            $item->sku,
            $item->item_type === 'service' ? __('Service') : __('Physical'),
            number_format($item->unit_price, 2),
            $item->vat_rate,
            $item->is_active ? __('Active') : __('Inactive'),
        ];
    }

    public function create()
    {
        return view('user.items.form', [
            'item' => new Item,
            'units' => Unit::orderBy('name')->get(),
            'taxRates' => TaxRate::active()->effectiveAsOf()->orderByDesc('is_default')->orderBy('name')->get(),
            'customFieldDefinitions' => Item::customFieldDefinitions(),
            'customFieldValues' => [],
        ]);
    }

    public function store(Request $request)
    {
        if (app(UsageLimitService::class)->reached(Auth::user()->company, 'products')) {
            return back()->withErrors(['plan_limit' => app(UsageLimitService::class)->friendlyMessage(Auth::user()->company, 'products')])->withInput();
        }

        $data = $this->validated($request);
        $altUnits = $data['alt_units'] ?? [];
        $customFields = $data['custom_fields'] ?? [];
        unset($data['alt_units'], $data['custom_fields']);
        $data = $this->withImage($request, $data);
        $item = Item::create($data);
        $this->syncAltUnits($item, $altUnits);
        $item->syncCustomFieldValues($customFields);

        return redirect()->route('app.items.index')->with('status', __('Item saved.'));
    }

    public function edit(Item $item)
    {
        $item->load('customFieldValues');

        return view('user.items.form', [
            'item' => $item,
            'units' => Unit::orderBy('name')->get(),
            'taxRates' => TaxRate::active()->effectiveAsOf()->orderByDesc('is_default')->orderBy('name')->get(),
            'customFieldDefinitions' => Item::customFieldDefinitions(),
            'customFieldValues' => $item->customFieldValuesMap(),
        ]);
    }

    public function update(Request $request, Item $item)
    {
        $data = $this->validated($request);
        $altUnits = $data['alt_units'] ?? [];
        $customFields = $data['custom_fields'] ?? [];
        unset($data['alt_units'], $data['custom_fields']);
        $data = $this->withImage($request, $data, $item);
        $item->update($data);
        $this->syncAltUnits($item, $altUnits);
        $item->syncCustomFieldValues($customFields);

        return redirect()->route('app.items.index')->with('status', __('Item updated.'));
    }

    private function syncAltUnits(Item $item, array $altUnits): void
    {
        $item->itemUnits()->delete();

        foreach ($altUnits as $row) {
            if (empty($row['unit_id']) || (int) $row['unit_id'] === $item->base_unit_id) {
                continue;
            }

            ItemUnit::create([
                'item_id' => $item->id,
                'unit_id' => $row['unit_id'],
                'conversion_factor' => $row['conversion_factor'] ?? 1,
                'unit_price' => ($row['unit_price'] ?? '') !== '' ? $row['unit_price'] : null,
            ]);
        }
    }

    public function destroy(Item $item)
    {
        if ($item->image_path) {
            Storage::disk('public')->delete($item->image_path);
        }

        $item->delete();

        return redirect()->route('app.items.index')->with('status', __('Item deleted.'));
    }

    public function showImport()
    {
        return view('user.items.import');
    }

    public function importTemplate()
    {
        $csv = "name,item_type,sku,barcode,category,unit_price,purchase_price,vat_rate\n"
            .'"Portland Cement 50kg",physical,CEM-50,,Building Materials,25.00,18.00,15'."\n";

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="items-template.csv"',
        ]);
    }

    public function import(Request $request)
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt']]);

        $companyId = Auth::user()->company_id;

        $result = $this->runCsvImport(
            $request->file('file'),
            function (array $row) use ($companyId) {
                return Validator::make([
                    'name' => $row['name'] ?: null,
                    'item_type' => strtolower($row['item_type'] ?? '') === 'service' ? 'service' : 'physical',
                    'sku' => $row['sku'] ?: null,
                    'barcode' => $row['barcode'] ?: null,
                    'category' => $row['category'] ?: null,
                    'unit_price' => $row['unit_price'] ?: null,
                    'purchase_price' => $row['purchase_price'] ?: null,
                    'vat_rate' => $row['vat_rate'] !== '' ? $row['vat_rate'] : TaxRate::defaultRate($companyId),
                ], [
                    'name' => ['required', 'string', 'max:255'],
                    'item_type' => ['required', 'in:service,physical'],
                    'sku' => ['nullable', 'string', 'max:40'],
                    'barcode' => ['nullable', 'string', 'max:64'],
                    'category' => ['nullable', 'string', 'max:100'],
                    'unit_price' => ['required', 'numeric', 'min:0'],
                    'purchase_price' => ['nullable', 'numeric', 'min:0'],
                    'vat_rate' => ['required', 'numeric', 'min:0', 'max:100'],
                ])->validate();
            },
            function (array $data) use ($companyId) {
                Item::create($data + [
                    'company_id' => $companyId,
                    'unit' => 'unit',
                    'unit_code' => 'PCE',
                    'base_unit_id' => Unit::where('company_id', $companyId)->where('code', 'PCE')->value('id'),
                    'is_active' => true,
                    'track_inventory' => false,
                ]);
            }
        );

        AuditLog::record('item.import', null, __(':count item(s) imported via CSV', ['count' => $result['imported']]));

        return redirect()->route('app.items.import')->with('import_result', $result);
    }

    public function generateBarcode()
    {
        do {
            $barcode = (string) random_int(100000000000, 999999999999);
        } while (Item::where('barcode', $barcode)->exists());

        return response()->json(['barcode' => $barcode]);
    }

    private function withImage(Request $request, array $data, ?Item $item = null): array
    {
        if ($request->hasFile('image')) {
            if ($item?->image_path) {
                Storage::disk('public')->delete($item->image_path);
            }

            $data['image_path'] = $request->file('image')->store('items', 'public');
        }

        return $data;
    }

    private function validated(Request $request): array
    {
        $companyId = Auth::user()->company_id;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'sku' => ['nullable', 'string', 'max:40'],
            'barcode' => ['nullable', 'string', 'max:64'],
            'category' => ['nullable', 'string', 'max:100'],
            'expiry_date' => ['nullable', 'date'],
            'item_type' => ['required', 'in:service,physical'],
            'base_unit_id' => ['nullable', Rule::exists('units', 'id')->where('company_id', $companyId)],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'vat_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'reorder_point' => ['nullable', 'numeric', 'min:0'],
            'image' => ['nullable', 'image', 'max:2048'],
            'alt_units' => ['nullable', 'array'],
            'alt_units.*.unit_id' => ['nullable', Rule::exists('units', 'id')->where('company_id', $companyId)],
            'alt_units.*.conversion_factor' => ['nullable', 'numeric', 'min:0.0001'],
            'alt_units.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'custom_fields' => ['nullable', 'array'],
            'custom_fields.*' => ['nullable', 'string', 'max:2000'],
        ]);

        unset($data['image']);

        $data['is_active'] = $request->boolean('is_active', true);
        $data['track_inventory'] = $data['item_type'] === 'physical' && $request->boolean('track_inventory');

        $baseUnit = ! empty($data['base_unit_id']) ? Unit::find($data['base_unit_id']) : null;

        // The form's "None (defaults to Piece / PCE)" option only holds
        // that promise if something here actually assigns Piece — left
        // as null, the item never gets a base_unit_id, and the unit
        // picker on invoice/quotation/bill/PO forms stays permanently
        // disabled for it (it only enables once the selected item has at
        // least one unit).
        if (! $baseUnit) {
            $baseUnit = Unit::where('company_id', $companyId)->where('code', 'PCE')->first();
            $data['base_unit_id'] = $baseUnit?->id;
        }

        $data['unit'] = $baseUnit->name ?? 'unit';
        $data['unit_code'] = $baseUnit ? ($baseUnit->code ?: 'PCE') : 'PCE';

        $this->validateRequiredCustomFields(Item::customFieldDefinitions(), $data['custom_fields'] ?? []);

        return $data;
    }

    private function validateRequiredCustomFields($definitions, array $submitted): void
    {
        $errors = [];
        foreach ($definitions as $definition) {
            if (! $definition->is_required) {
                continue;
            }
            $value = $submitted[$definition->id] ?? null;
            if ($value === null || $value === '') {
                $errors["custom_fields.{$definition->id}"] = __(':field is required.', ['field' => $definition->label]);
            }
        }

        if ($errors) {
            throw \Illuminate\Validation\ValidationException::withMessages($errors);
        }
    }
}
