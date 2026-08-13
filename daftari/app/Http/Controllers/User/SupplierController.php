<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SupplierController extends Controller
{
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

    public function store(Request $request)
    {
        Supplier::create($this->validated($request));

        return redirect()->route('app.suppliers.index')->with('status', __('Supplier saved.'));
    }

    public function edit(Supplier $supplier)
    {
        return view('user.suppliers.form', compact('supplier'));
    }

    public function update(Request $request, Supplier $supplier)
    {
        $supplier->update($this->validated($request, $supplier));

        return redirect()->route('app.suppliers.index')->with('status', __('Supplier updated.'));
    }

    public function destroy(Supplier $supplier)
    {
        if ($supplier->bills()->exists() || $supplier->purchaseOrders()->exists()) {
            return back()->withErrors(['supplier' => __('This supplier has bills or purchase orders and cannot be deleted.')]);
        }

        $supplier->delete();

        return redirect()->route('app.suppliers.index')->with('status', __('Supplier deleted.'));
    }

    private function validated(Request $request, ?Supplier $supplier = null): array
    {
        $companyId = Auth::user()->company_id;

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'vat_number' => ['nullable', 'string', 'max:32'],
            'cr_number' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
