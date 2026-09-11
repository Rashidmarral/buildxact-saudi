<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\Quotation;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GlobalSearchController extends Controller
{
    /**
     * Limit per category so the dropdown stays short — this is a quick
     * jump-to tool, not a full search-results page. Only categories the
     * user actually has permission to view are searched, so results never
     * reveal records their role couldn't otherwise reach.
     */
    private const LIMIT = 5;

    public function search(Request $request)
    {
        $term = trim((string) $request->query('q', ''));

        if (mb_strlen($term) < 2) {
            return response()->json(['groups' => []]);
        }

        $like = '%'.$term.'%';
        $user = Auth::user();
        $groups = [];

        if ($user->hasPermission('clients')) {
            $clients = Client::where(fn ($q) => $q->where('name', 'like', $like)
                ->orWhere('client_code', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhere('vat_number', 'like', $like))
                ->orderBy('name')->limit(self::LIMIT)->get();

            if ($clients->isNotEmpty()) {
                $groups[] = [
                    'label' => __('Clients'),
                    'items' => $clients->map(fn ($c) => [
                        'title' => $c->name,
                        'subtitle' => $c->client_code,
                        'url' => route('app.clients.edit', $c),
                    ]),
                ];
            }
        }

        if ($user->hasPermission('purchases')) {
            $suppliers = Supplier::where(fn ($q) => $q->where('name', 'like', $like)
                ->orWhere('supplier_code', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhere('vat_number', 'like', $like))
                ->orderBy('name')->limit(self::LIMIT)->get();

            if ($suppliers->isNotEmpty()) {
                $groups[] = [
                    'label' => __('Suppliers'),
                    'items' => $suppliers->map(fn ($s) => [
                        'title' => $s->name,
                        'subtitle' => $s->supplier_code,
                        'url' => route('app.suppliers.edit', $s),
                    ]),
                ];
            }

            $bills = Bill::with('supplier')->where('bill_number', 'like', $like)
                ->orderByDesc('bill_date')->limit(self::LIMIT)->get();

            if ($bills->isNotEmpty()) {
                $groups[] = [
                    'label' => __('Bills'),
                    'items' => $bills->map(fn ($b) => [
                        'title' => $b->bill_number,
                        'subtitle' => $b->supplier->name,
                        'url' => route('app.bills.show', $b),
                    ]),
                ];
            }

            $orders = PurchaseOrder::with('supplier')->where('po_number', 'like', $like)
                ->orderByDesc('order_date')->limit(self::LIMIT)->get();

            if ($orders->isNotEmpty()) {
                $groups[] = [
                    'label' => __('Purchase orders'),
                    'items' => $orders->map(fn ($o) => [
                        'title' => $o->po_number,
                        'subtitle' => $o->supplier->name,
                        'url' => route('app.purchase-orders.show', $o),
                    ]),
                ];
            }
        }

        if ($user->hasPermission('items')) {
            $items = Item::where(fn ($q) => $q->where('name', 'like', $like)
                ->orWhere('sku', 'like', $like)
                ->orWhere('barcode', 'like', $like))
                ->orderBy('name')->limit(self::LIMIT)->get();

            if ($items->isNotEmpty()) {
                $groups[] = [
                    'label' => __('Items'),
                    'items' => $items->map(fn ($i) => [
                        'title' => $i->name,
                        'subtitle' => $i->sku,
                        'url' => route('app.items.edit', $i),
                    ]),
                ];
            }
        }

        if ($user->hasPermission('invoices')) {
            $invoices = Invoice::with('client')->where(fn ($q) => $q->where('invoice_number', 'like', $like)
                ->orWhereHas('client', fn ($cq) => $cq->where('name', 'like', $like)))
                ->orderByDesc('issue_date')->limit(self::LIMIT)->get();

            if ($invoices->isNotEmpty()) {
                $groups[] = [
                    'label' => __('Invoices'),
                    'items' => $invoices->map(fn ($inv) => [
                        'title' => $inv->invoice_number,
                        'subtitle' => $inv->client->name,
                        'url' => route('app.invoices.show', $inv),
                    ]),
                ];
            }
        }

        if ($user->hasPermission('quotations')) {
            $quotations = Quotation::with('client')->where(fn ($q) => $q->where('quotation_number', 'like', $like)
                ->orWhereHas('client', fn ($cq) => $cq->where('name', 'like', $like)))
                ->orderByDesc('issue_date')->limit(self::LIMIT)->get();

            if ($quotations->isNotEmpty()) {
                $groups[] = [
                    'label' => __('Quotations'),
                    'items' => $quotations->map(fn ($q) => [
                        'title' => $q->quotation_number,
                        'subtitle' => $q->client->name,
                        'url' => route('app.quotations.show', $q),
                    ]),
                ];
            }
        }

        return response()->json(['groups' => $groups]);
    }
}
