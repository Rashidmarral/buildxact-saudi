<?php

namespace App\Http\Controllers\User\Restaurant;

use App\Http\Controllers\Controller;
use App\Models\RestaurantTable;
use Illuminate\Http\Request;

class RestaurantTableController extends Controller
{
    public function index()
    {
        return view('user.restaurant.tables.index', [
            'tables' => RestaurantTable::withCount(['orders' => fn ($q) => $q->whereNotIn('status', ['completed', 'cancelled'])])
                ->orderBy('area')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $table = RestaurantTable::create($this->validated($request) + ['status' => 'available']);

        return redirect()->route('app.restaurant.tables.index')->with('status', __('Table saved: :name', ['name' => $table->name]));
    }

    public function update(Request $request, RestaurantTable $table)
    {
        $table->update($this->validated($request));

        return redirect()->route('app.restaurant.tables.index')->with('status', __('Table updated.'));
    }

    public function destroy(RestaurantTable $table)
    {
        if ($table->orders()->exists()) {
            return back()->withErrors(['table' => __('This table has order history and cannot be deleted.')]);
        }

        $table->delete();

        return redirect()->route('app.restaurant.tables.index')->with('status', __('Table deleted.'));
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:30'],
            'area' => ['nullable', 'string', 'max:60'],
            'seats' => ['required', 'integer', 'min:1', 'max:100'],
        ]);
    }
}
