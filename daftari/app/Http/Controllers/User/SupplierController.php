<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Controllers\User\Concerns\ResolvesPerPage;
use App\Http\Controllers\User\Concerns\ExportsCsv;
use App\Http\Controllers\User\Concerns\ImportsCsv;
use App\Models\AuditLog;
use App\Models\Supplier;
use App\Rules\SaudiCrNumber;
use App\Rules\SaudiPhoneNumber;
use App\Rules\SaudiVatNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class SupplierController extends Controller
{
    use ExportsCsv, ImportsCsv, ResolvesPerPage;

    public function index(Request $request)
    {
        $query = Supplier::orderBy('name');

        if ($request->query('export') === 'csv') {
            return $this->csvResponse(
                'suppliers.csv',
                [__('Code'), __('Name'), __('VAT number'), __('Email'), __('Phone')],
                $query->get()->map(fn ($supplier) => [
                    $supplier->supplier_code,
                    $supplier->name,
                    $supplier->vat_number,
                    $supplier->email,
                    $supplier->phone,
                ])
            );
        }

        $suppliers = $query->paginate($this->resolvePerPage($request))->withQueryString();

        return view('user.suppliers.index', compact('suppliers'));
    }

    public function create()
    {
        return view('user.suppliers.form', [
            'supplier' => new Supplier,
            'customFieldDefinitions' => Supplier::customFieldDefinitions(),
            'customFieldValues' => [],
        ]);
    }

    public function outstandingBills(Supplier $supplier)
    {
        $bills = $supplier->bills()
            ->where('status', 'posted')
            ->with('items')
            ->get()
            ->filter(fn ($bill) => $bill->balanceDue() > 0)
            ->map(fn ($bill) => [
                'id' => $bill->id,
                'bill_number' => $bill->bill_number,
                'date' => $bill->bill_date->format('Y-m-d'),
                'total' => number_format((float) $bill->total, 2),
                'balance' => number_format($bill->balanceDue(), 2),
                'items' => $bill->items->map(fn ($line) => [
                    'description' => $line->description,
                    'quantity' => rtrim(rtrim(number_format((float) $line->quantity, 2), '0'), '.'),
                ])->values(),
            ])
            ->values();

        return response()->json($bills);
    }

    public function bills(Supplier $supplier)
    {
        $bills = $supplier->bills()
            ->where('status', '!=', 'void')
            ->orderByDesc('bill_date')
            ->get()
            ->map(fn ($bill) => [
                'id' => $bill->id,
                'bill_number' => $bill->bill_number,
                'bill_date' => $bill->bill_date->format('Y-m-d'),
                'total' => number_format($bill->total, 2),
            ])
            ->values();

        return response()->json($bills);
    }

    public function store(Request $request)
    {
        if (Auth::user()->company->hasReachedPlanLimit('suppliers')) {
            return back()->withErrors(['plan_limit' => __('You have reached your plan\'s supplier limit. Upgrade your plan to add more suppliers.')])->withInput();
        }

        $data = $this->validated($request);
        $customFields = $data['custom_fields'] ?? [];
        unset($data['custom_fields']);

        $supplier = Supplier::create($data);
        $supplier->syncCustomFieldValues($customFields);

        AuditLog::record('supplier.create', $supplier, __('Created supplier :name', ['name' => $supplier->name]));

        return redirect()->route('app.suppliers.index')->with('status', __('Supplier saved.'));
    }

    public function edit(Supplier $supplier)
    {
        $supplier->load('customFieldValues');

        return view('user.suppliers.form', [
            'supplier' => $supplier,
            'customFieldDefinitions' => Supplier::customFieldDefinitions(),
            'customFieldValues' => $supplier->customFieldValuesMap(),
        ]);
    }

    public function update(Request $request, Supplier $supplier)
    {
        $data = $this->validated($request, $supplier);
        $customFields = $data['custom_fields'] ?? [];
        unset($data['custom_fields']);

        $supplier->update($data);
        $supplier->syncCustomFieldValues($customFields);

        AuditLog::record('supplier.update', $supplier, __('Updated supplier :name', ['name' => $supplier->name]));

        return redirect()->route('app.suppliers.index')->with('status', __('Supplier updated.'));
    }

    public function destroy(Supplier $supplier)
    {
        if ($supplier->bills()->exists() || $supplier->purchaseOrders()->exists()) {
            return back()->withErrors(['supplier' => __('This supplier has bills or purchase orders and cannot be deleted.')]);
        }

        AuditLog::record('supplier.delete', $supplier, __('Deleted supplier :name', ['name' => $supplier->name]));

        $supplier->delete();

        return redirect()->route('app.suppliers.index')->with('status', __('Supplier deleted.'));
    }

    /**
     * Audit finding LOW-29: mirrors Client::logContact() — a one-click
     * way to timestamp "I just talked to this supplier" without opening
     * the edit form.
     */
    public function logContact(Supplier $supplier)
    {
        $supplier->update(['last_contacted_at' => now()]);

        return back()->with('status', __('Contact logged for :name.', ['name' => $supplier->name]));
    }

    public function showImport()
    {
        return view('user.suppliers.import');
    }

    public function importTemplate()
    {
        $csv = "name,type,email,phone,vat_number,cr_number,city,notes\n"
            .'"Gulf Building Materials Est.",company,sales@example.com,0512345678,300012345600003,1010123456,Jeddah,"Preferred supplier"'."\n";

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="suppliers-template.csv"',
        ]);
    }

    public function import(Request $request)
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt']]);

        $company = Auth::user()->company;
        $companyId = $company->id;

        $plan = $company->activeSubscription()?->plan;
        $maxRows = $plan && $plan->max_suppliers !== null
            ? max(0, $plan->max_suppliers - $company->suppliers()->count())
            : null;

        $result = $this->runCsvImport(
            $request->file('file'),
            function (array $row) {
                return Validator::make([
                    'type' => strtolower($row['type'] ?? '') === 'individual' ? 'individual' : 'company',
                    'name' => $row['name'] ?: null,
                    'email' => $row['email'] ?: null,
                    'phone' => $row['phone'] ?: null,
                    'vat_number' => $row['vat_number'] ?: null,
                    'cr_number' => $row['cr_number'] ?: null,
                    'city' => $row['city'] ?: null,
                    'notes' => $row['notes'] ?: null,
                ], [
                    'name' => ['required', 'string', 'max:255'],
                    'type' => ['required', 'in:individual,company'],
                    'email' => ['nullable', 'email', 'max:255'],
                    'phone' => ['nullable', 'string', new SaudiPhoneNumber],
                    'vat_number' => ['nullable', 'string', new SaudiVatNumber],
                    'cr_number' => ['nullable', 'string', new SaudiCrNumber],
                    'city' => ['nullable', 'string', 'max:100'],
                    'notes' => ['nullable', 'string', 'max:2000'],
                ])->validate();
            },
            function (array $data) use ($companyId) {
                Supplier::create($data + ['company_id' => $companyId]);
            },
            $maxRows
        );

        AuditLog::record('supplier.import', null, __(':count supplier(s) imported via CSV', ['count' => $result['imported']]));

        return redirect()->route('app.suppliers.import')->with('import_result', $result);
    }

    private function validated(Request $request, ?Supplier $supplier = null): array
    {
        $companyId = Auth::user()->company_id;

        $data = $request->validate([
            'supplier_code' => ['nullable', 'string', 'max:20'],
            'type' => ['required', 'in:individual,company'],
            'name' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'vat_number' => ['nullable', 'string', new SaudiVatNumber],
            'cr_number' => ['nullable', 'string', new SaudiCrNumber],
            'initial_balance' => ['nullable', 'numeric'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', new SaudiPhoneNumber],
            'mobile' => ['nullable', 'string', new SaudiPhoneNumber],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'state' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lead_source' => ['nullable', 'string', 'max:100'],
            'next_follow_up_date' => ['nullable', 'date'],
            'custom_fields' => ['nullable', 'array'],
            'custom_fields.*' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->validateRequiredCustomFields(Supplier::customFieldDefinitions(), $data['custom_fields'] ?? []);

        // "Non-resident" is what the checkbox asks for; is_resident is its
        // inverse and defaults to true (most suppliers are Saudi-resident)
        // since an unchecked checkbox never appears in the request.
        $data['is_resident'] = ! $request->boolean('is_non_resident');

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
