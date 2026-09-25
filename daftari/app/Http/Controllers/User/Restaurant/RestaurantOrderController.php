<?php

namespace App\Http\Controllers\User\Restaurant;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Item;
use App\Models\RestaurantOrder;
use App\Models\RestaurantOrderItem;
use App\Models\RestaurantTable;
use App\Models\SmsConfig;
use App\Models\WhatsappConfig;
use App\Services\Accounting\LedgerPostingService;
use App\Services\Restaurant\RestaurantOrderService;
use App\Services\SmsService;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use RuntimeException;

class RestaurantOrderController extends Controller
{
    public function index()
    {
        return view('user.restaurant.orders.index', [
            'openOrders' => RestaurantOrder::with('table')->whereNotIn('status', ['completed', 'cancelled'])->latest()->get(),
            'tables' => RestaurantTable::orderBy('area')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, RestaurantOrderService $service)
    {
        $companyId = Auth::user()->company_id;

        $data = $request->validate([
            'order_type' => ['required', 'in:dine_in,takeaway'],
            'table_id' => ['required_if:order_type,dine_in', 'nullable', Rule::exists('restaurant_tables', 'id')->where('company_id', $companyId)],
            'customer_name' => ['nullable', 'string', 'max:120'],
            'customer_phone' => ['nullable', 'string', 'max:30'],
        ]);

        try {
            $order = $service->createOrder(Auth::user()->company, $data);
        } catch (RuntimeException $e) {
            return back()->withErrors(['order' => $e->getMessage()]);
        }

        return redirect()->route('app.restaurant.orders.show', $order);
    }

    public function show(RestaurantOrder $order)
    {
        $order->load(['items.item', 'table', 'sale']);

        return view('user.restaurant.orders.show', compact('order'));
    }

    public function lookupItem(Request $request)
    {
        $query = trim((string) $request->query('q'));

        $items = Item::where('is_active', true)
            ->where(function ($q) use ($query) {
                $q->where('barcode', $query)
                    ->orWhere('sku', 'like', "%{$query}%")
                    ->orWhere('name', 'like', "%{$query}%")
                    ->orWhere('name_ar', 'like', "%{$query}%");
            })
            ->take(15)
            ->get()
            ->map(fn ($item) => [
                'id' => $item->id,
                'name' => $item->name,
                'unit_price' => (float) $item->unit_price,
                'vat_rate' => (float) $item->vat_rate,
            ])
            ->values();

        return response()->json($items);
    }

    public function storeItems(Request $request, RestaurantOrder $order, RestaurantOrderService $service)
    {
        $companyId = Auth::user()->company_id;

        $data = $request->validate([
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['required', Rule::exists('items', 'id')->where('company_id', $companyId)],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'lines.*.notes' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $service->addItems($order, $data['lines']);
        } catch (RuntimeException $e) {
            return back()->withErrors(['items' => $e->getMessage()]);
        }

        return back()->with('status', __('Sent to kitchen.'));
    }

    public function updateItemStatus(Request $request, RestaurantOrderItem $item, RestaurantOrderService $service)
    {
        $data = $request->validate([
            'kitchen_status' => ['required', 'in:pending,preparing,ready,served'],
        ]);

        $service->markItemStatus($item, $data['kitchen_status']);

        if ($request->wantsJson()) {
            return response()->json(['status' => $item->fresh()->kitchen_status, 'order_status' => $item->order->fresh()->status]);
        }

        return back()->with('status', __('Item updated.'));
    }

    public function checkout(Request $request, RestaurantOrder $order, RestaurantOrderService $service, LedgerPostingService $ledger)
    {
        $companyId = Auth::user()->company_id;

        $data = $request->validate([
            'register_id' => ['nullable', Rule::exists('pos_registers', 'id')->where('company_id', $companyId)],
            'payments' => ['required', 'array', 'min:1'],
            'payments.*.method' => ['required', 'in:cash,card,other'],
            'payments.*.amount' => ['required', 'numeric', 'min:0.01'],
            'payments.*.reference' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $sale = $service->checkout($order, $data['payments'], $ledger, $data['register_id'] ?? null);
        } catch (RuntimeException $e) {
            return back()->withErrors(['checkout' => $e->getMessage()]);
        }

        AuditLog::record('restaurant_order.checkout', $order, __('Checked out order :number', ['number' => $order->order_number]));

        return redirect()->route('app.pos.sales.show', $sale)->with('status', __('Order completed.'));
    }

    public function cancel(Request $request, RestaurantOrder $order, RestaurantOrderService $service)
    {
        $data = $request->validate(['cancel_reason' => ['required', 'string', 'max:255']]);

        try {
            $service->cancelOrder($order, $data['cancel_reason']);
        } catch (RuntimeException $e) {
            return back()->withErrors(['order' => $e->getMessage()]);
        }

        AuditLog::record('restaurant_order.cancel', $order, __('Cancelled order :number', ['number' => $order->order_number]));

        return redirect()->route('app.restaurant.orders.index')->with('status', __('Order cancelled.'));
    }

    /**
     * "Ready for pickup" only makes sense for a takeaway order — a dine-in
     * customer is already seated at the table the order belongs to.
     */
    private function readyMessage(RestaurantOrder $order): string
    {
        return __(':company: your order is ready for pickup. Order :number.', [
            'company' => $order->company->name,
            'number' => $order->order_number,
        ]);
    }

    public function notifySms(RestaurantOrder $order, SmsService $sms)
    {
        abort_unless($order->order_type === 'takeaway', 404);

        $config = SmsConfig::first();
        abort_unless($config && $config->is_enabled, 404);

        if (! $order->customer_phone) {
            return back()->withErrors(['order' => __('This order has no customer phone number on file.')]);
        }

        $result = $sms->send($config, $order->customer_phone, $this->readyMessage($order));

        if (! $result['success']) {
            return back()->withErrors(['order' => __('SMS send failed: :error', ['error' => $result['error']])]);
        }

        AuditLog::record('restaurant_order.sms_sent', $order, __('Sent "ready for pickup" SMS for order :number', ['number' => $order->order_number]));

        return back()->with('status', __('SMS sent.'));
    }

    public function notifyWhatsapp(RestaurantOrder $order, WhatsAppService $whatsapp)
    {
        abort_unless($order->order_type === 'takeaway', 404);

        $config = WhatsappConfig::first();
        abort_unless($config && $config->is_enabled, 404);

        if (! $order->customer_phone) {
            return back()->withErrors(['order' => __('This order has no customer phone number on file.')]);
        }

        $result = $whatsapp->sendReadyTemplateMessage($config, $order->customer_phone, [
            $order->customer_name ?: __('Customer'),
            __('your takeaway order'),
            $order->order_number,
        ]);

        if (! $result['success']) {
            return back()->withErrors(['order' => __('WhatsApp send failed: :error', ['error' => $result['error']])]);
        }

        AuditLog::record('restaurant_order.whatsapp_sent', $order, __('Sent "ready for pickup" WhatsApp message for order :number', ['number' => $order->order_number]));

        return back()->with('status', __('WhatsApp message sent.'));
    }
}
