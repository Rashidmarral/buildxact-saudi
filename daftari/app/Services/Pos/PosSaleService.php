<?php

namespace App\Services\Pos;

use App\Models\Item;
use App\Models\ItemStock;
use App\Models\PosRegister;
use App\Models\PosSale;
use App\Models\PosSaleItem;
use App\Models\PosShift;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Turns a checkout cart into a completed, paid, stock-deducted,
 * GL-posted POS sale — the "ring up a sale" half of the module (see
 * PosShiftService for the till-session half).
 */
class PosSaleService
{
    /**
     * @param  array<int, array{item_id: int, quantity: float, unit_price?: float, discount_amount?: float}>  $cartLines
     * @param  array<int, array{method: string, amount: float, reference?: string}>  $payments
     */
    public function checkout(PosShift $shift, array $cartLines, array $payments, ?int $clientId, LedgerPostingService $ledger): PosSale
    {
        if (empty($cartLines)) {
            throw new RuntimeException(__('The cart is empty.'));
        }

        $register = $shift->register;
        $company = $shift->company;

        return DB::transaction(function () use ($shift, $register, $company, $cartLines, $payments, $clientId, $ledger) {
            $sale = PosSale::create([
                'shift_id' => $shift->id,
                'register_id' => $register->id,
                'warehouse_id' => $register->warehouse_id,
                'client_id' => $clientId,
                'sale_number' => $company->nextPosSaleNumber(),
                'status' => 'completed',
                'created_by' => Auth::id(),
            ]);

            $subtotal = 0.0;
            $vatTotal = 0.0;
            $discountTotal = 0.0;

            foreach ($cartLines as $line) {
                $item = Item::findOrFail($line['item_id']);
                $quantity = (float) $line['quantity'];
                $unitPrice = (float) ($line['unit_price'] ?? $item->unit_price);
                $discount = (float) ($line['discount_amount'] ?? 0);
                $lineNet = round(($quantity * $unitPrice) - $discount, 2);
                $vatRate = (float) $item->vat_rate;
                $vatAmount = round($lineNet * ($vatRate / 100), 2);

                PosSaleItem::create([
                    'pos_sale_id' => $sale->id,
                    'item_id' => $item->id,
                    'description' => $item->name,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'discount_amount' => $discount,
                    'vat_rate' => $vatRate,
                    'vat_amount' => $vatAmount,
                    'line_total' => $lineNet,
                ]);

                if ($item->track_inventory && $register->warehouse_id) {
                    $this->adjustStock($item->id, $register->warehouse_id, -$quantity);
                }

                $subtotal += $lineNet;
                $vatTotal += $vatAmount;
                $discountTotal += $discount;
            }

            $subtotal = round($subtotal, 2);
            $vatTotal = round($vatTotal, 2);
            $total = round($subtotal + $vatTotal, 2);

            $paidTotal = round(array_sum(array_map(fn ($p) => (float) $p['amount'], $payments)), 2);

            if (abs($paidTotal - $total) > 0.01) {
                throw new RuntimeException(__('Payments (:paid) do not add up to the sale total (:total).', ['paid' => number_format($paidTotal, 2), 'total' => number_format($total, 2)]));
            }

            foreach ($payments as $payment) {
                $sale->payments()->create([
                    'method' => $payment['method'],
                    'amount' => $payment['amount'],
                    'reference' => $payment['reference'] ?? null,
                ]);
            }

            $sale->update([
                'subtotal' => $subtotal,
                'discount_total' => $discountTotal,
                'vat_total' => $vatTotal,
                'total' => $total,
            ]);

            $ledger->postPosSale($sale->fresh('payments'));

            return $sale->fresh(['items', 'payments']);
        });
    }

    public function void(PosSale $sale, string $reason, LedgerPostingService $ledger): void
    {
        if ($sale->status !== 'completed') {
            throw new RuntimeException(__('This sale has already been voided.'));
        }

        DB::transaction(function () use ($sale, $reason, $ledger) {
            $sale->loadMissing('items');

            if ($sale->warehouse_id) {
                foreach ($sale->items as $line) {
                    if ($line->item_id && $line->item?->track_inventory) {
                        $this->adjustStock($line->item_id, $sale->warehouse_id, (float) $line->quantity);
                    }
                }
            }

            $ledger->reverse($sale->company, 'pos_sale', $sale->id, __('POS sale :number voided', ['number' => $sale->sale_number]));

            $sale->update(['status' => 'void', 'voided_at' => now(), 'void_reason' => $reason]);
        });
    }

    private function adjustStock(int $itemId, int $warehouseId, float $delta): void
    {
        $stock = ItemStock::firstOrCreate(
            ['item_id' => $itemId, 'warehouse_id' => $warehouseId],
            ['quantity' => 0]
        );

        $stock->increment('quantity', $delta);
    }
}
