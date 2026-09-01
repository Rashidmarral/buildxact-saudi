<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\TaxRate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class TaxRateController extends Controller
{
    public function index()
    {
        return view('user.settings.tax-rates', [
            'taxRates' => TaxRate::orderByDesc('is_default')->orderBy('name')->get(),
            'types' => TaxRate::TYPES,
            'company' => Auth::user()->company,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $taxRate = TaxRate::create($data + ['company_id' => Auth::user()->company_id]);

        if ($request->boolean('is_default')) {
            $taxRate->makeDefault();
        }

        return redirect()->route('app.tax-rates.index')->with('status', __('Tax rate saved.'));
    }

    public function update(Request $request, TaxRate $taxRate)
    {
        $data = $this->validated($request);
        $taxRate->update($data);

        if ($request->boolean('is_default')) {
            $taxRate->makeDefault();
        }

        return redirect()->route('app.tax-rates.index')->with('status', __('Tax rate updated.'));
    }

    /**
     * Input VAT on general (non-directly-attributable) purchases is fully
     * recoverable by default — correct for the vast majority of companies.
     * A company that also makes VAT-exempt supplies opts into
     * apportionment here; the recovery percentage is normally left blank
     * so the VAT report computes it itself each period from the
     * company's own exempt vs. taxable sales, the standard method — a
     * manual override is only for a company that already knows its
     * agreed recovery rate.
     */
    public function updateRecovery(Request $request)
    {
        $data = $request->validate([
            'vat_makes_exempt_supplies' => ['nullable', 'boolean'],
            'vat_recovery_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        Auth::user()->company->update([
            'vat_makes_exempt_supplies' => $request->boolean('vat_makes_exempt_supplies'),
            'vat_recovery_percentage' => $data['vat_recovery_percentage'] ?? null,
        ]);

        return redirect()->route('app.tax-rates.index')->with('status', __('VAT recovery settings saved.'));
    }

    public function destroy(TaxRate $taxRate)
    {
        if ($taxRate->is_default) {
            return back()->withErrors(['tax_rate' => __('Cannot delete the default tax rate. Make another rate the default first.')]);
        }

        if ($this->isInUse($taxRate)) {
            return back()->withErrors(['tax_rate' => __('This tax rate is referenced by existing documents and cannot be deleted. Deactivate it instead.')]);
        }

        $taxRate->delete();

        return redirect()->route('app.tax-rates.index')->with('status', __('Tax rate deleted.'));
    }

    private function isInUse(TaxRate $taxRate): bool
    {
        return \App\Models\InvoiceItem::where('tax_rate_id', $taxRate->id)->exists()
            || \App\Models\CreditNoteItem::where('tax_rate_id', $taxRate->id)->exists();
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'type' => ['required', Rule::in(array_keys(TaxRate::TYPES))],
            'effective_date' => ['nullable', 'date'],
        ]);

        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }
}
