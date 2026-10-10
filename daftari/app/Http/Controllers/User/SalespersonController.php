<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Salesperson;
use Illuminate\Http\Request;

class SalespersonController extends Controller
{
    public function index()
    {
        $salespersons = Salesperson::withCount('invoices', 'quotations')->orderBy('name')->get();

        return view('user.salespersons.index', compact('salespersons'));
    }

    public function create()
    {
        return view('user.salespersons.form', ['salesperson' => new Salesperson]);
    }

    public function store(Request $request)
    {
        Salesperson::create($this->validated($request));

        return redirect()->route('app.salespersons.index')->with('status', __('Salesperson saved.'));
    }

    public function edit(Salesperson $salesperson)
    {
        return view('user.salespersons.form', compact('salesperson'));
    }

    public function update(Request $request, Salesperson $salesperson)
    {
        $salesperson->update($this->validated($request));

        return redirect()->route('app.salespersons.index')->with('status', __('Salesperson updated.'));
    }

    public function destroy(Salesperson $salesperson)
    {
        $salesperson->delete();

        return redirect()->route('app.salespersons.index')->with('status', __('Salesperson deleted.'));
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'commission_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }
}
