<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
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
    use ImportsCsv;

    public function index()
    {
        $suppliers = Supplier::orderBy('name')->paginate(20);

        return view('user.suppliers.index', compact('suppliers'));
    }

    public function create()
    {
        return view('user.suppliers.form', ['supplier' => new Supplier]);
    }

    public function outstandingBills(Supplier $supplier)
    {
        $bills = $supplier->bills()
            ->where('status', 'posted')
            ->get()
            ->filter(fn ($bill) => $bill->balanceDue() > 0)
            ->map(fn ($bill) => [
                'id' => $bill->id,
                'bill_number' => $bill->bill_number,
                'balance' => number_format($bill->balanceDue(), 2),
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

        $supplier = Supplier::create($this->validated($request));

        AuditLog::record('supplier.create', $supplier, __('Created supplier :name', ['name' => $supplier->name]));

        return redirect()->route('app.suppliers.index')->with('status', __('Supplier saved.'));
    }

    public function edit(Supplier $supplier)
    {
        return view('user.suppliers.form', compact('supplier'));
    }

    public function update(Request $request, Supplier $supplier)
    {
        $supplier->update($this->validated($request, $supplier));

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

        return $request->validate([
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
        ]);
    }
}
