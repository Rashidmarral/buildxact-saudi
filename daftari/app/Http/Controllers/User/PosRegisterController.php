<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Branch;
use App\Models\PosRegister;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PosRegisterController extends Controller
{
    public function index()
    {
        $company = Auth::user()->company;
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);

        return view('user.pos-registers.index', [
            'registers' => PosRegister::with(['branch', 'warehouse'])->orderBy('name')->get(),
            'branches' => Branch::orderBy('name')->get(),
            'warehouses' => Warehouse::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $register = PosRegister::create($this->validated($request));

        return redirect()->route('app.pos-registers.index')->with('status', __('Register saved: :name', ['name' => $register->name]));
    }

    public function update(Request $request, PosRegister $register)
    {
        $register->update($this->validated($request));

        return redirect()->route('app.pos-registers.index')->with('status', __('Register updated.'));
    }

    public function destroy(PosRegister $register)
    {
        if ($register->shifts()->exists()) {
            return back()->withErrors(['register' => __('This register has shift history and cannot be deleted. Deactivate it instead.')]);
        }

        $register->delete();

        return redirect()->route('app.pos-registers.index')->with('status', __('Register deleted.'));
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'branch_id' => ['nullable', 'exists:branches,id'],
            'warehouse_id' => ['nullable', 'exists:warehouses,id'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }
}
