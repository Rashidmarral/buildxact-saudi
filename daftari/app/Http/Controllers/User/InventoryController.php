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
     * against cost at the item's current purchase price (the same cost
     * basis the Valuation tab already uses) — the profitability view
     * that was missing next to plain quantity/value reporting.
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
                $cost = $quantity * (float) ($item->purchase_price ?? 0);
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
