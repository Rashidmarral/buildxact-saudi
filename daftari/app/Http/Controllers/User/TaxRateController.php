<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\TaxRate;
use App\Support\Zatca\VatExemptionReason;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TaxRateController extends Controller
{
    public function index()
    {
        return view('user.settings.tax-rates', [
            'taxRates' => TaxRate::orderByDesc('is_default')->orderBy('name')->get(),
            'types' => TaxRate::TYPES,
            'exemptionReasonsByType' => [
                TaxRate::TYPE_ZERO_RATED => VatExemptionReason::optionsFor(TaxRate::TYPE_ZERO_RATED),
                TaxRate::TYPE_EXEMPT => VatExemptionReason::optionsFor(TaxRate::TYPE_EXEMPT),
            ],
            'notSubjectType' => TaxRate::TYPE_NOT_SUBJECT,
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

        // Audit finding SEC-08: tax-rate configuration (the number that
        // decides how much VAT every invoice/bill/expense charges or
        // recovers) had no audit trail at all.
        AuditLog::record('tax_rate.create', $taxRate, __('Created tax rate ":name" (:rate% :type)', ['name' => $taxRate->name, 'rate' => $taxRate->rate, 'type' => $taxRate->type]));

        return redirect()->route('app.tax-rates.index')->with('status', __('Tax rate saved.'));
    }

    public function update(Request $request, TaxRate $taxRate)
    {
        $old = $taxRate->only(['name', 'rate', 'type', 'is_active', 'exemption_reason_code', 'exemption_reason_text']);
        $data = $this->validated($request);
        $taxRate->update($data);

        if ($request->boolean('is_default')) {
            $taxRate->makeDefault();
        }

        AuditLog::record('tax_rate.update', $taxRate, __('Updated tax rate ":name"', ['name' => $taxRate->name]), null, $old, $taxRate->only(array_keys($old)));

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

        $company = Auth::user()->company;
        $old = $company->only(['vat_makes_exempt_supplies', 'vat_recovery_percentage']);

        $company->update([
            'vat_makes_exempt_supplies' => $request->boolean('vat_makes_exempt_supplies'),
            'vat_recovery_percentage' => $data['vat_recovery_percentage'] ?? null,
        ]);

        AuditLog::record('tax_rate.recovery_update', $company, __('Updated VAT recovery/apportionment settings'), null, $old, $company->only(array_keys($old)));

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

        AuditLog::record('tax_rate.delete', $taxRate, __('Deleted tax rate ":name"', ['name' => $taxRate->name]));

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
            'exemption_reason_code' => ['nullable', 'string', 'max:20'],
            'exemption_reason_text' => ['nullable', 'string', 'max:1000'],
            'effective_date' => ['nullable', 'date'],
        ]);

        $data['is_active'] = $request->boolean('is_active', true);

        // ZATCA (BR-KSA-23/24/69/78, BR-KSA-83) requires an exemption
        // reason on every Zero-rated/Exempt/Not-subject-to-VAT rate, and
        // (BR-S-10) forbids one on Standard.
        if ($data['type'] === TaxRate::TYPE_NOT_SUBJECT) {
            // VAT category O has no fixed reason text list — the spec
            // itself says it's free text the taxpayer supplies per case,
            // so the code is always the one fixed VATEX-SA-OOS value and
            // only the text is user-entered.
            if (empty($data['exemption_reason_text'])) {
                throw ValidationException::withMessages([
                    'exemption_reason_text' => __('Describe why this supply is not subject to VAT — ZATCA requires this free-text reason.'),
                ]);
            }
            $data['exemption_reason_code'] = 'VATEX-SA-OOS';
        } elseif (in_array($data['type'], [TaxRate::TYPE_ZERO_RATED, TaxRate::TYPE_EXEMPT], true)) {
            if (empty($data['exemption_reason_code']) || ! VatExemptionReason::isValidFor($data['exemption_reason_code'], $data['type'])) {
                throw ValidationException::withMessages([
                    'exemption_reason_code' => __('Select a ZATCA exemption reason for this VAT category.'),
                ]);
            }
            $data['exemption_reason_text'] = null;
        } else {
            $data['exemption_reason_code'] = null;
            $data['exemption_reason_text'] = null;
        }

        return $data;
    }
}
