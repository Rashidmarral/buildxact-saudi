<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Services\Accounting\LedgerPostingService;
use App\Services\MpdfRenderer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PurchaseReturnController extends Controller
{
    private const ELIGIBLE_STATUSES = ['posted'];

    public function index()
    {
        $purchaseReturns = PurchaseReturn::with('supplier', 'bill')
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->paginate(20);

        return view('user.purchase-returns.index', compact('purchaseReturns'));
    }

    public function eligibleBills(Request $request)
    {
        $search = trim((string) $request->query('q'));

        $bills = Bill::with('supplier')
            ->whereIn('status', self::ELIGIBLE_STATUSES)
            ->when($search !== '', fn ($q) => $q->where(fn ($q2) => $q2
                ->where('bill_number', 'like', "%{$search}%")
                ->orWhereHas('supplier', fn ($q3) => $q3->where('name', 'like', "%{$search}%"))))
            ->latest('bill_date')
            ->take(20)
            ->get()
            ->filter(fn ($bill) => $bill->remainingReturnableTotal() > 0.01)
            ->map(fn ($bill) => [
                'id' => $bill->id,
                'bill_number' => $bill->bill_number,
                'supplier_name' => $bill->supplier->name,
                'bill_date' => $bill->bill_date->format('Y-m-d'),
                'total' => number_format($bill->total, 2),
                'returnable' => number_format($bill->remainingReturnableTotal(), 2),
            ])
            ->values();

        return response()->json($bills);
    }

    public function create(Request $request)
    {
        $billId = $request->query('bill_id');

        if (! $billId) {
            return view('user.purchase-returns.select-bill');
        }

        $bill = Bill::with('items.item')->findOrFail($billId);

        if (! in_array($bill->status, self::ELIGIBLE_STATUSES, true) || $bill->remainingReturnableTotal() <= 0.01) {
            return redirect()->route('app.purchase-returns.create')->withErrors(['bill' => __('This bill is no longer eligible for a purchase return.')]);
        }

        $company = Auth::user()->company;

        return view('user.purchase-returns.form', [
            'bill' => $bill,
            'nextNumberPreview' => $company->purchase_return_prefix.'-'.str_pad((string) $company->next_purchase_return_number, 5, '0', STR_PAD_LEFT),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'bill_id' => ['required', Rule::exists('bills', 'id')],
            'issue_date' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.bill_item_id' => ['nullable', Rule::exists('bill_items', 'id')],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'min:0'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.vat_rate' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $data['items'] = array_values(array_filter($data['items'], fn ($row) => (float) $row['quantity'] > 0));

        if (empty($data['items'])) {
            return back()->withErrors(['items' => __('Return at least one line item.')])->withInput();
        }

        $bill = Bill::findOrFail($data['bill_id']);

        $requestedTotal = array_sum(array_map(function ($row) {
            $lineSubtotal = (float) $row['quantity'] * (float) $row['unit_price'];

            return $lineSubtotal + round($lineSubtotal * ((float) $row['vat_rate'] / 100), 2);
        }, $data['items']));

        if ($requestedTotal - $bill->remainingReturnableTotal() > 0.01) {
            return back()->withErrors(['items' => __('This purchase return exceeds the amount remaining on the bill.')])->withInput();
        }

        $purchaseReturn = DB::transaction(function () use ($data, $bill) {
            $company = Auth::user()->company;

            $purchaseReturn = PurchaseReturn::create([
                'bill_id' => $bill->id,
                'supplier_id' => $bill->supplier_id,
                'branch_id' => $bill->branch_id,
                'created_by' => Auth::id(),
                'return_number' => $company->nextPurchaseReturnNumber(),
                'issue_date' => $data['issue_date'],
                'reason' => $data['reason'] ?? null,
                'status' => 'issued',
                'currency' => $bill->currency,
            ]);

            foreach ($data['items'] as $row) {
                $sourceLine = ! empty($row['bill_item_id'])
                    ? BillItem::find($row['bill_item_id'])
                    : null;

                $item = new PurchaseReturnItem([
                    'purchase_return_id' => $purchaseReturn->id,
                    'bill_item_id' => $row['bill_item_id'] ?? null,
                    'unit_id' => $sourceLine?->unit_id,
                    'description' => $row['description'],
                    'quantity' => $row['quantity'],
                    'unit_price' => $row['unit_price'],
                    'vat_rate' => $row['vat_rate'],
                ]);
                $item->recalculate();
                $item->save();
            }

            $purchaseReturn->recalculateTotals();

            return $purchaseReturn;
        });

        app(LedgerPostingService::class)->postPurchaseReturn($purchaseReturn);

        return redirect()->route('app.purchase-returns.show', $purchaseReturn)->with('status', __('Purchase return issued.'));
    }

    public function show(PurchaseReturn $purchaseReturn)
    {
        $purchaseReturn->load('items', 'supplier', 'bill');
        $template = $purchaseReturn->company->defaultTemplateFor('bill');

        return view('user.purchase-returns.show', compact('purchaseReturn', 'template'));
    }

    public function downloadPdf(PurchaseReturn $purchaseReturn, MpdfRenderer $renderer)
    {
        $purchaseReturn->loadMissing('items', 'supplier', 'bill');

        $doc = [
            'type_label' => __('Purchase Return'),
            'type_label_ar' => 'مرتجع مشتريات',
            'number' => $purchaseReturn->return_number,
            'date_label' => __('Issued'),
            'date' => $purchaseReturn->issue_date,
            'ref_no' => $purchaseReturn->bill->bill_number,
            'party_label' => __('Return to'),
            'party_label_ar' => 'المورد',
            'party' => $purchaseReturn->supplier,
            'lines' => $purchaseReturn->items,
            'subtotal' => $purchaseReturn->subtotal,
            'vat_total' => $purchaseReturn->vat_total,
            'total' => $purchaseReturn->total,
            'notes' => $purchaseReturn->reason,
        ];

        $pdf = $renderer->render('documents.print.pdf', [
            'doc' => $doc,
            'company' => $purchaseReturn->company,
            'template' => $purchaseReturn->company->defaultTemplateFor('bill'),
        ]);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$purchaseReturn->return_number.'.pdf"',
        ]);
    }

    public function void(PurchaseReturn $purchaseReturn, LedgerPostingService $ledger)
    {
        if ($purchaseReturn->status === 'void') {
            return back();
        }

        $ledger->reverse($purchaseReturn->company, 'purchase_return', $purchaseReturn->id, __('Purchase return :number voided', ['number' => $purchaseReturn->return_number]));
        $purchaseReturn->update(['status' => 'void']);

        return back()->with('status', __('Purchase return voided.'));
    }
}
