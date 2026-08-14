<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class WarehouseController extends Controller
{
    public function index()
    {
        return view('user.warehouses.index', [
            'warehouses' => Warehouse::with('branch')->withCount('stocks')->orderBy('name')->get(),
            'branches' => Branch::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $isDefault = $request->boolean('is_default');
        unset($data['is_default']);

        $warehouse = Warehouse::create($data);

        if ($isDefault || ! Warehouse::where('branch_id', $warehouse->branch_id)->where('id', '!=', $warehouse->id)->exists()) {
            $this->makeDefaultWithinBranch($warehouse);
        }

        return redirect()->route('app.warehouses.index')->with('status', __('Warehouse saved.'));
    }

    public function update(Request $request, Warehouse $warehouse)
    {
        $data = $this->validated($request);
        $isDefault = $request->boolean('is_default');
        unset($data['is_default']);

        $warehouse->update($data);

        if ($isDefault) {
            $this->makeDefaultWithinBranch($warehouse);
        }

        return redirect()->route('app.warehouses.index')->with('status', __('Warehouse updated.'));
    }

    public function destroy(Warehouse $warehouse)
    {
        if ($warehouse->stocks()->where('quantity', '>', 0)->exists()) {
            return back()->withErrors(['warehouse' => __('This warehouse still holds stock and cannot be deleted.')]);
        }

        $warehouse->delete();

        return redirect()->route('app.warehouses.index')->with('status', __('Warehouse deleted.'));
    }

    public function makeDefault(Warehouse $warehouse)
    {
        $this->makeDefaultWithinBranch($warehouse);

        return back()->with('status', __('Default warehouse updated.'));
    }

    private function makeDefaultWithinBranch(Warehouse $warehouse): void
    {
        Warehouse::where('company_id', Auth::user()->company_id)
            ->where('branch_id', $warehouse->branch_id)
            ->update(['is_default' => false]);

        $warehouse->update(['is_default' => true]);
    }

    private function validated(Request $request): array
    {
        $companyId = Auth::user()->company_id;

        return $request->validate([
            'code' => ['nullable', 'string', 'max:20'],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('company_id', $companyId)],
            'name' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);
    }
}
