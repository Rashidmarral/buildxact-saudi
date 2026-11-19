<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Controllers\User\Concerns\ExportsCsv;
use App\Http\Controllers\User\Concerns\ResolvesPerPage;
use App\Http\Controllers\User\Concerns\ResolvesReportPeriod;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\ItemStock;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    use ExportsCsv, ResolvesPerPage, ResolvesReportPeriod;

    public function stock(Request $request)
    {
        $stocks = ItemStock::with('item', 'warehouse')
            ->whereHas('item', fn ($q) => $q->where('track_inventory', true))
            ->orderBy(Item::select('name')->whereColumn('items.id', 'item_stocks.item_id'))
            ->paginate($this->resolvePerPage($request))
            ->withQueryString();

        return view('user.inventory.stock', compact('stocks'));
    }

    public function valuation()
    {
        $stocks = ItemStock::with('item', 'warehouse')
            ->whereHas('item', fn ($q) => $q->where('track_inventory', true))
            ->get()
            ->sortBy(fn ($stock) => $stock->item->name)
            ->map(fn ($stock) => [
                'item' => $stock->item,
                'warehouse' => $stock->warehouse,
                'quantity' => (float) $stock->quantity,
                'avg_cost' => (float) ($stock->item->purchase_price ?? 0),
                'total_value' => (float) $stock->quantity * (float) ($stock->item->purchase_price ?? 0),
            ]);

        return view('user.inventory.valuation', [
            'rows' => $stocks,
            'grandTotal' => $stocks->sum('total_value'),
        ]);
    }

    /**
     * Margin per item sold in the period: revenue from invoice lines
     * against each line's own frozen cost at the time it was created
     * (InvoiceController::syncItems()'s unit_cost snapshot — audit
     * finding INV-04) rather than the item's CURRENT purchase price. A
     * report for a past period must reflect what that sale actually cost
     * back then; recomputing from today's live cost silently shows a
     * different margin than the books actually recorded if the item's
     * cost has since changed. A line created before this snapshot
     * existed has no unit_cost on file and falls back to the item's
     * current price — a known approximation for pre-migration data only.
     */
    public function profitability(Request $request)
    {
        $period = $this->resolvePeriod($request);

        $rows = InvoiceItem::with('item')
            ->whereNotNull('item_id')
            ->whereHas('invoice', fn ($q) => $q->whereNotIn('status', ['draft', 'cancelled'])->whereBetween('issue_date', [$period['from'], $period['to']]))
            ->get()
            ->groupBy('item_id')
            ->filter(fn ($lines) => $lines->first()->item !== null)
            ->map(function ($lines) {
                $item = $lines->first()->item;
                $quantity = (float) $lines->sum('quantity');
                $revenue = (float) $lines->sum(fn (InvoiceItem $l) => $l->quantity * $l->unit_price);
                $cost = (float) $lines->sum(function (InvoiceItem $l) use ($item) {
                    $unitCost = $l->unit_cost !== null ? (float) $l->unit_cost : (float) ($item->purchase_price ?? 0);

                    return (float) $l->quantity * $unitCost;
                });
                $margin = $revenue - $cost;

                return [
                    'item' => $item,
                    'quantity' => $quantity,
                    'revenue' => $revenue,
                    'cost' => $cost,
                    'margin' => $margin,
                    'margin_percent' => $revenue > 0.004 ? ($margin / $revenue) * 100 : 0.0,
                ];
            })
            ->sortByDesc('margin')
            ->values();

        $totals = [
            'revenue' => (float) $rows->sum('revenue'),
            'cost' => (float) $rows->sum('cost'),
            'margin' => (float) $rows->sum('margin'),
        ];

        if ($request->query('export') === 'csv') {
            return $this->csvResponse('item-profitability.csv',
                [__('Item'), __('Qty sold'), __('Revenue'), __('Cost'), __('Margin'), __('Margin %')],
                $rows->map(fn (array $row) => [
                    $row['item']->name,
                    rtrim(rtrim(number_format($row['quantity'], 2, '.', ''), '0'), '.'),
                    number_format($row['revenue'], 2, '.', ''),
                    number_format($row['cost'], 2, '.', ''),
                    number_format($row['margin'], 2, '.', ''),
                    number_format($row['margin_percent'], 1, '.', ''),
                ]));
        }

        return view('user.inventory.profitability', [
            'rows' => $rows,
            'totals' => $totals,
            'period' => $period,
        ]);
    }
}
