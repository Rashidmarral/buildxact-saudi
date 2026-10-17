<?php

namespace App\Http\Controllers\User\Restaurant;

use App\Http\Controllers\Controller;
use App\Models\RestaurantOrder;

class RestaurantKitchenController extends Controller
{
    public function index()
    {
        return view('user.restaurant.kitchen.index', ['orders' => $this->activeOrders()]);
    }

    /**
     * Polled every few seconds by the kitchen display board (see
     * resources/views/user/restaurant/kitchen/index.blade.php) — returns
     * the same board partial rendered fresh, rather than a JSON payload
     * the page would have to re-render client side.
     */
    public function feed()
    {
        return view('user.restaurant.kitchen._board', ['orders' => $this->activeOrders()]);
    }

    private function activeOrders()
    {
        return RestaurantOrder::with(['items', 'table'])
            ->whereIn('status', ['kitchen', 'ready'])
            ->oldest()
            ->get();
    }
}
