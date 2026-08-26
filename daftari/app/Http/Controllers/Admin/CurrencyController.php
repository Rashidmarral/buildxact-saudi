<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Currency;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CurrencyController extends Controller
{
    public function index()
    {
        $currencies = Currency::orderBy('sort_order')->orderBy('code')->get();

        return view('admin.currencies.index', compact('currencies'));
    }

    public function create()
    {
        return view('admin.currencies.form', ['currency' => new Currency]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $currency = Currency::create($data);

        if ($request->boolean('is_default')) {
            $currency->makeDefault();
        }

        return redirect()->route('admin.currencies.index')->with('status', __('Currency created.'));
    }

    public function edit(Currency $currency)
    {
        return view('admin.currencies.form', compact('currency'));
    }

    public function update(Request $request, Currency $currency)
    {
        $data = $this->validated($request, $currency);
        $currency->update($data);

        if ($request->boolean('is_default')) {
            $currency->makeDefault();
        }

        return redirect()->route('admin.currencies.index')->with('status', __('Currency updated.'));
    }

    public function destroy(Currency $currency)
    {
        if ($currency->is_default) {
            return back()->withErrors(['currency' => __('Cannot delete the default currency. Make another currency the default first.')]);
        }

        if (Company::where('currency', $currency->code)->exists()) {
            return back()->withErrors(['currency' => __('This currency is in use by at least one company and cannot be deleted. Deactivate it instead.')]);
        }

        $currency->delete();

        return redirect()->route('admin.currencies.index')->with('status', __('Currency deleted.'));
    }

    private function validated(Request $request, ?Currency $currency = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'size:3', 'alpha', 'uppercase', Rule::unique('currencies', 'code')->ignore($currency)],
            'name' => ['required', 'string', 'max:255'],
            'symbol' => ['required', 'string', 'max:10'],
            'decimal_places' => ['required', 'integer', 'min:0', 'max:4'],
            'decimal_separator' => ['required', 'string', 'size:1'],
            'thousands_separator' => ['required', 'string', 'size:1'],
            'symbol_position' => ['required', Rule::in(Currency::SYMBOL_POSITIONS)],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $data['code'] = strtoupper($data['code']);
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['is_active'] = $request->boolean('is_active', true) || $request->boolean('is_default');

        return $data;
    }
}
