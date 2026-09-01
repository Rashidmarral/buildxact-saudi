<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CustomsDeclaration;
use App\Models\Supplier;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CustomsDeclarationController extends Controller
{
    public function index()
    {
        $declarations = CustomsDeclaration::with('supplier', 'bills')
            ->orderByDesc('declaration_date')
            ->orderByDesc('id')
            ->paginate(20);

        return view('user.customs-declarations.index', [
            'declarations' => $declarations,
            'suppliers' => Supplier::orderBy('name')->get(),
            'totalCount' => CustomsDeclaration::count(),
            'totalVat' => CustomsDeclaration::sum('vat_amount'),
        ]);
    }

    public function store(Request $request, LedgerPostingService $ledger)
    {
        $data = $this->validated($request);
        $billIds = $data['bill_ids'] ?? [];
        unset($data['bill_ids']);

        $rate = (float) $data['vat_rate'];
        $base = (float) $data['customs_value'] + (float) $data['customs_duty'];
        $data['vat_amount'] = round($base * $rate / 100, 2);
        $data['created_by'] = Auth::id();

        DB::transaction(function () use ($data, $billIds, $ledger) {
            $declaration = CustomsDeclaration::create($data);

            if (! empty($billIds)) {
                $declaration->bills()->sync($billIds);
            }

            $ledger->postCustomsDeclaration($declaration);
        });

        return redirect()->route('app.customs-declarations.index')->with('status', __('Customs declaration recorded.'));
    }

    /**
     * Audit finding LOW-24: customs_duty on a declaration always posted
     * straight to a P&L expense account (postCustomsDeclaration()), even
     * for goods still sitting in inventory — real landed cost means that
     * duty is part of what the goods cost, not a period expense. This
     * spreads the duty across the linked bills' tracked-inventory lines
     * (proportional to line value) and bumps each item's purchase_price
     * by its per-unit share, then reclassifies the same amount from
     * expense to Inventory Asset on the ledger (postLandedCostAllocation).
     * One-shot per declaration — landed_cost_allocated_at guards against
     * double-counting if the button is clicked twice.
     */
    public function allocateLandedCost(CustomsDeclaration $customsDeclaration, LedgerPostingService $ledger)
    {
        if ($customsDeclaration->landed_cost_allocated_at) {
            return back()->withErrors(['landed_cost' => __('Landed cost has already been allocated for this declaration.')]);
        }

        $duty = (float) $customsDeclaration->customs_duty;

        if ($duty <= 0) {
            return back()->withErrors(['landed_cost' => __('This declaration has no customs duty to allocate.')]);
        }

        $customsDeclaration->loadMissing('bills.items.item');

        $totalBillValue = 0.0;
        $trackedLines = collect();

        foreach ($customsDeclaration->bills as $bill) {
            $totalBillValue += (float) $bill->subtotal;

            foreach ($bill->items as $line) {
                if ($line->item?->track_inventory) {
                    $trackedLines->push([
                        'item' => $line->item,
                        'quantity' => (float) $line->quantity,
                        'value' => (float) $line->quantity * (float) $line->unit_price,
                    ]);
                }
            }
        }

        $trackedValue = (float) $trackedLines->sum('value');

        if ($trackedValue <= 0 || $totalBillValue <= 0) {
            return back()->withErrors(['landed_cost' => __('The linked bills have no tracked-inventory line items to allocate landed cost to.')]);
        }

        $trackedShare = min($trackedValue / $totalBillValue, 1.0);
        $capitalizedDuty = round($duty * $trackedShare, 2);

        DB::transaction(function () use ($customsDeclaration, $trackedLines, $trackedValue, $capitalizedDuty, $ledger) {
            foreach ($trackedLines->groupBy(fn ($row) => $row['item']->id) as $rows) {
                $item = $rows->first()['item'];
                $itemValue = $rows->sum('value');
                $itemQuantity = $rows->sum('quantity');
                $itemDuty = $capitalizedDuty * ($itemValue / $trackedValue);

                if ($itemQuantity > 0) {
                    $item->increment('purchase_price', round($itemDuty / $itemQuantity, 2));
                }
            }

            $ledger->postLandedCostAllocation($customsDeclaration, $capitalizedDuty);

            $customsDeclaration->update(['landed_cost_allocated_at' => now()]);
        });

        AuditLog::record('customs_declaration.allocate_landed_cost', $customsDeclaration, __('Allocated :amount of landed cost across item costs', [
            'amount' => rtrim(rtrim(number_format($capitalizedDuty, 2), '0'), '.'),
        ]));

        return back()->with('status', __('Landed cost allocated to item costs.'));
    }

    public function destroy(CustomsDeclaration $customsDeclaration, LedgerPostingService $ledger)
    {
        DB::transaction(function () use ($customsDeclaration, $ledger) {
            $ledger->reverse($customsDeclaration->company, 'customs_declaration', $customsDeclaration->id, __('Customs declaration deleted'));
            $customsDeclaration->delete();
        });

        return redirect()->route('app.customs-declarations.index')->with('status', __('Customs declaration deleted.'));
    }

    private function validated(Request $request): array
    {
        $companyId = Auth::user()->company_id;

        return $request->validate([
            'declaration_number' => ['nullable', 'string', 'max:255'],
            'declaration_date' => ['required', 'date'],
            'port_of_entry' => ['nullable', 'string', 'max:255'],
            'supplier_id' => ['nullable', Rule::exists('suppliers', 'id')->where('company_id', $companyId)],
            'customs_value' => ['required', 'numeric', 'min:0'],
            'customs_duty' => ['nullable', 'numeric', 'min:0'],
            'vat_rate' => ['required', 'numeric', 'in:15,0'],
            'sadad_reference' => ['nullable', 'string', 'max:255'],
            'bill_ids' => ['nullable', 'array'],
            'bill_ids.*' => [Rule::exists('bills', 'id')->where('company_id', $companyId)],
        ]);
    }
}
