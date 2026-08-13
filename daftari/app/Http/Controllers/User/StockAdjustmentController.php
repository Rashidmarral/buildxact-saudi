<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\StockAdjustment;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StockAdjustmentController extends Controller
{
    public function index()
    {
        $adjustments = StockAdjustment::with('item', 'warehouse')->latest('date')->latest('id')->paginate(20);

        return view('user.stock-adjustments.index', compact('adjustments'));
    }

    public function create()
    {
        return view('user.stock-adjustments.form', [
            'items' => Item::where('track_inventory', true)->orderBy('name')->get(),
            'warehouses' => Warehouse::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $adjustment = DB::transaction(function () use ($data) {
            $adjustment = StockAdjustment::create([
                'item_id' => $data['item_id'],
                'warehouse_id' => $data['warehouse_id'],
                'created_by' => Auth::id(),
                'type' => $data['type'],
                'quantity' => $data['quantity'],
                'reason' => $data['reason'] ?? null,
                'date' => $data['date'],
                'status' => 'recorded',
                'notes' => $data['notes'] ?? null,
            ]);

            $this->applyToStock($adjustment, 1);

            return $adjustment;
        });

        return redirect()->route('app.stock-adjustments.index')->with('status', __('Stock adjustment recorded.'));
    }

    public function revoke(StockAdjustment $stockAdjustment)
    {
        if ($stockAdjustment->status === 'revoked') {
            return back();
        }

        DB::transaction(function () use ($stockAdjustment) {
            $this->applyToStock($stockAdjustment, -1);
            $stockAdjustment->update(['status' => 'revoked']);
        });

        return back()->with('status', __('Stock adjustment revoked.'));
    }

    private function applyToStock(StockAdjustment $adjustment, int $direction): void
    {
        $stock = ItemStock::firstOrCreate(
            ['item_id' => $adjustment->item_id, 'warehouse_id' => $adjustment->warehouse_id],
            ['quantity' => 0]
        );

        $stock->increment('quantity', $adjustment->signedQuantity() * $direction);
    }

    private function validated(Request $request): array
    {
        $companyId = Auth::user()->company_id;

        return $request->validate([
            'item_id' => ['required', Rule::exists('items', 'id')->where('company_id', $companyId)],
            'warehouse_id' => ['required', Rule::exists('warehouses', 'id')->where('company_id', $companyId)],
            'type' => ['required', 'in:increase,decrease'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['nullable', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
