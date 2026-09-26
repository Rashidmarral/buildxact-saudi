<?php

namespace App\Services\Restaurant;

use App\Models\Company;
use App\Models\Item;
use App\Models\LoyaltyCard;
use App\Models\PosRegister;
use App\Models\PosSale;
use App\Models\RestaurantOrder;
use App\Models\RestaurantOrderItem;
use App\Models\RestaurantTable;
use App\Services\Accounting\LedgerPostingService;
use App\Services\CoffeeShop\LoyaltyCardService;
use App\Services\Pos\PosSaleService;
use App\Services\Pos\PosShiftService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Dine-in/takeaway order lifecycle: open an order (optionally against a
 * table), add rounds of items for the kitchen, track each line's kitchen
 * status, and checkout — which reuses PosSaleService as-is (same GL
 * posting, stock deduction and payment recording a retail POS sale gets)
 * rather than re-implementing any of that for restaurant orders. Coffee
 * Shop (a thin Restaurant preset, see FeatureRegistry) adds one more
 * payment method on top of this same flow: a loyalty card balance,
 * redeemed via LoyaltyCardService once the sale exists (see checkout()).
 */
class RestaurantOrderService
{
    public function __construct(
        private PosShiftService $shiftService,
        private PosSaleService $saleService,
        private LoyaltyCardService $loyaltyCardService,
    ) {}

    /**
     * @param  array{order_type: string, table_id?: int|null, customer_name?: string|null, customer_phone?: string|null}  $data
     */
    public function createOrder(Company $company, array $data): RestaurantOrder
    {
        $table = null;

        if ($data['order_type'] === 'dine_in') {
            $table = RestaurantTable::findOrFail($data['table_id']);

            if ($table->status !== 'available') {
                throw new RuntimeException(__('This table is not available.'));
            }
        }

        return DB::transaction(function () use ($company, $data, $table) {
            $order = RestaurantOrder::create([
                'company_id' => $company->id,
                'order_number' => $company->nextRestaurantOrderNumber(),
                'order_type' => $data['order_type'],
                'table_id' => $table?->id,
                'customer_name' => $data['customer_name'] ?? null,
                'customer_phone' => $data['customer_phone'] ?? null,
                'status' => 'open',
                'created_by' => Auth::id(),
            ]);

            $table?->update(['status' => 'occupied']);

            return $order;
        });
    }

    /**
     * A "round" sent to the kitchen: appends new line items (each starting
     * out 'pending') and flips the order back into 'kitchen' status even
     * if earlier rounds had already reached 'ready'.
     *
     * @param  array<int, array{item_id: int, quantity: float, unit_price?: float, notes?: string|null}>  $lines
     */
    public function addItems(RestaurantOrder $order, array $lines): void
    {
        if (! $order->isOpen()) {
            throw new RuntimeException(__('This order is already closed.'));
        }

        if (empty($lines)) {
            throw new RuntimeException(__('Add at least one item.'));
        }

        DB::transaction(function () use ($order, $lines) {
            foreach ($lines as $line) {
                $item = Item::findOrFail($line['item_id']);

                RestaurantOrderItem::create([
                    'company_id' => $order->company_id,
                    'restaurant_order_id' => $order->id,
                    'item_id' => $item->id,
                    'description' => $item->name,
                    'quantity' => (float) $line['quantity'],
                    'unit_price' => (float) ($line['unit_price'] ?? $item->unit_price),
                    'vat_rate' => (float) $item->vat_rate,
                    'notes' => $line['notes'] ?? null,
                    'kitchen_status' => 'pending',
                ]);
            }

            $this->recomputeStatus($order);
        });
    }

    public function markItemStatus(RestaurantOrderItem $item, string $status): void
    {
        if (! in_array($status, ['pending', 'preparing', 'ready', 'served'], true)) {
            throw new RuntimeException(__('Invalid kitchen status.'));
        }

        $item->update(['kitchen_status' => $status]);

        $this->recomputeStatus($item->order);
    }

    /**
     * 'kitchen' while any line is still pending/preparing; 'ready' once
     * every line has reached ready/served; otherwise left untouched (an
     * order with no items yet stays 'open').
     */
    private function recomputeStatus(RestaurantOrder $order): void
    {
        if (! $order->isOpen()) {
            return;
        }

        $items = $order->items()->get(['kitchen_status']);

        if ($items->isEmpty()) {
            return;
        }

        $status = $items->whereIn('kitchen_status', ['pending', 'preparing'])->isNotEmpty() ? 'kitchen' : 'ready';

        if ($order->status !== $status) {
            $order->update(['status' => $status]);
        }
    }

    /**
     * @param  array<int, array{method: string, amount: float, reference?: string, loyalty_card_id?: int}>  $payments
     */
    public function checkout(RestaurantOrder $order, array $payments, LedgerPostingService $ledger, ?int $registerId = null): PosSale
    {
        return DB::transaction(function () use ($order, $payments, $ledger, $registerId) {
            // Locks the order row for the duration of the transaction so a
            // double-click or two concurrent tabs can't both pass the
            // isOpen() check and both check the order out — without this, a
            // race here creates two PosSale rows (double GL posting, double
            // stock deduction) for one physical order.
            $order = RestaurantOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if (! $order->isOpen()) {
                throw new RuntimeException(__('This order is already closed.'));
            }

            $order->loadMissing('items');

            if ($order->items->isEmpty()) {
                throw new RuntimeException(__('Add items to the order before checkout.'));
            }

            $register = $registerId
                ? PosRegister::findOrFail($registerId)
                : $this->resolveRegister($order->company);

            $shift = $register->openShift() ?? $this->shiftService->open($register, 0);

            $cartLines = $order->items->map(fn (RestaurantOrderItem $line) => [
                'item_id' => $line->item_id,
                'quantity' => (float) $line->quantity,
                'unit_price' => (float) $line->unit_price,
            ])->all();

            // PosSaleService only understands cash/card/other — a loyalty
            // card line is rewritten to 'other' here (so its own total
            // validation still balances) and actually redeemed below, once
            // the sale it's paying for exists to attach the redemption to.
            $loyaltyLines = [];
            $saleLines = [];

            foreach ($payments as $payment) {
                if (($payment['method'] ?? null) !== 'loyalty_card') {
                    $saleLines[] = $payment;

                    continue;
                }

                $card = LoyaltyCard::where('company_id', $order->company_id)->findOrFail($payment['loyalty_card_id'] ?? null);
                $loyaltyLines[] = ['card' => $card, 'amount' => (float) $payment['amount']];
                $saleLines[] = [
                    'method' => 'other',
                    'amount' => $payment['amount'],
                    'reference' => __('Loyalty card :number', ['number' => $card->card_number]),
                ];
            }

            $sale = $this->saleService->checkout($shift, $cartLines, $saleLines, null, $ledger);

            foreach ($loyaltyLines as $line) {
                $this->loyaltyCardService->redeem($line['card'], $line['amount'], $sale);
            }

            $order->update(['status' => 'completed', 'pos_sale_id' => $sale->id, 'completed_at' => now()]);
            $order->table?->update(['status' => 'available']);

            return $sale;
        });
    }

    public function cancelOrder(RestaurantOrder $order, string $reason): void
    {
        if (! $order->isOpen()) {
            throw new RuntimeException(__('This order is already closed.'));
        }

        $order->update(['status' => 'cancelled', 'cancel_reason' => $reason]);
        $order->table?->update(['status' => 'available']);
    }

    /**
     * Restaurant checkout reuses the same PosRegister/PosShift machinery a
     * retail POS sale goes through, but a restaurant company shouldn't
     * have to visit the separate POS module first just to set one up — a
     * single company-wide "Restaurant" register is created on first use.
     */
    private function resolveRegister(Company $company): PosRegister
    {
        return PosRegister::withoutGlobalScopes()->firstOrCreate(
            ['company_id' => $company->id, 'name' => 'Restaurant'],
            ['is_active' => true]
        );
    }
}
