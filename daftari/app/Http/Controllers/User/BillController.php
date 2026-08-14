<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\Item;
use App\Models\Supplier;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BillController extends Controller
{
    public function index(Request $request)
    {
        $bills = Bill::with('supplier')
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->orderByDesc('bill_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $counts = [
            'all' => Bill::count(),
            'draft' => Bill::where('status', 'draft')->count(),
            'posted' => Bill::where('status', 'posted')->count(),
            'void' => Bill::where('status', 'void')->count(),
        ];

        return view('user.bills.index', compact('bills', 'counts'));
    }

    public function create()
    {
        $company = Auth::user()->company;

        return view('user.bills.form', [
            'bill' => new Bill(['bill_date' => now()->toDateString(), 'due_date' => now()->addDays(30)->toDateString()]),
            'suppliers' => Supplier::orderBy('name')->get(),
            'items' => Item::where('is_active', true)->orderBy('name')->get(),
            'nextNumberPreview' => $company->bill_prefix.'-'.str_pad((string) $company->next_bill_number, 5, '0', STR_PAD_LEFT),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $bill = DB::transaction(function () use ($data) {
            $company = Auth::user()->company;

            $bill = Bill::create([
                'supplier_id' => $data['supplier_id'],
                'branch_id' => $company->default_branch_id,
                'created_by' => Auth::id(),
                'bill_number' => $company->nextBillNumber(),
                'supplier_reference' => $data['supplier_reference'] ?? null,
                'status' => 'draft',
                'bill_date' => $data['bill_date'],
                'due_date' => $data['due_date'] ?? null,
                'discount_total' => $data['discount_total'] ?? 0,
                'currency' => $company->currency,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->syncItems($bill, $data['items']);
            $bill->recalculateTotals();

            return $bill;
        });

        return redirect()->route('app.bills.show', $bill)->with('status', __('Bill created.'));
    }

    public function show(Bill $bill)
    {
        $bill->load('items', 'supplier', 'billPayments');

        return view('user.bills.show', compact('bill'));
    }

    public function post(Bill $bill, LedgerPostingService $ledger)
    {
        if ($bill->status === 'draft') {
            $bill->update(['status' => 'posted']);
            $ledger->postBillPosted($bill);
        }

        return back()->with('status', __('Bill posted.'));
    }

    public function void(Bill $bill, LedgerPostingService $ledger)
    {
        $bill->update(['status' => 'void']);
        $ledger->reverse($bill->company, 'bill', $bill->id, __('Bill :number voided', ['number' => $bill->bill_number]));

        return back()->with('status', __('Bill voided.'));
    }

    private function syncItems(Bill $bill, array $items): void
    {
        foreach ($items as $sort => $row) {
            $line = new BillItem([
                'bill_id' => $bill->id,
                'item_id' => $row['item_id'] ?? null,
                'description' => $row['description'],
                'quantity' => $row['quantity'],
                'unit_price' => $row['unit_price'],
                'vat_rate' => $row['vat_rate'] ?? 15,
                'sort_order' => $sort,
            ]);
            $line->recalculate();
            $line->save();
        }
    }

    private function validated(Request $request): array
    {
        $companyId = Auth::user()->company_id;

        return $request->validate([
            'supplier_id' => ['required', Rule::exists('suppliers', 'id')->where('company_id', $companyId)],
            'supplier_reference' => ['nullable', 'string', 'max:255'],
            'bill_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:bill_date'],
            'discount_total' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['nullable', Rule::exists('items', 'id')->where('company_id', $companyId)],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.vat_rate' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);
    }
}
