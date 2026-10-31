<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Currency;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CurrencyController extends Controller
{
    private const AUDITED_FIELDS = ['code', 'name', 'symbol', 'decimal_places', 'symbol_position', 'is_active'];

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

        AuditLog::record('currency.create', null, __('Created currency :code (:name)', ['code' => $currency->code, 'name' => $currency->name]), new: $currency->only(self::AUDITED_FIELDS));

        return redirect()->route('admin.currencies.index')->with('status', __('Currency created.'));
    }

    public function edit(Currency $currency)
    {
        return view('admin.currencies.form', compact('currency'));
    }

    public function update(Request $request, Currency $currency)
    {
        $old = $currency->only(self::AUDITED_FIELDS);

        $data = $this->validated($request, $currency);
        $currency->update($data);

        if ($request->boolean('is_default')) {
            $currency->makeDefault();
        }

        AuditLog::record('currency.update', null, __('Updated currency :code (:name)', ['code' => $currency->code, 'name' => $currency->name]), old: $old, new: $currency->only(self::AUDITED_FIELDS));

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

        $old = $currency->only(self::AUDITED_FIELDS);
        $currency->delete();

        AuditLog::record('currency.delete', null, __('Deleted currency :code (:name)', ['code' => $old['code'], 'name' => $old['name']]), old: $old);

        return redirect()->route('admin.currencies.index')->with('status', __('Currency deleted.'));
    }

    private function validated(Request $request, ?Currency $currency = null): array
    {
        // Normalize BEFORE validating (not after) — the 'uppercase' rule
        // below rejects a lowercase submission outright, so uppercasing
        // post-validation (as this used to) meant a currency code typed in
        // lowercase, e.g. "usd", was always rejected instead of accepted
        // and normalized. Same fix already applied in CouponController for
        // its own 'code' field.
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);

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

        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['is_active'] = $request->boolean('is_active', true) || $request->boolean('is_default');

        return $data;
    }
}
