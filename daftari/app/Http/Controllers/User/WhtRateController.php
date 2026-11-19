<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Models\WhtRate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class WhtRateController extends Controller
{
    public function index()
    {
        WhtRate::seedDefaults(Auth::user()->company_id);

        return view('user.settings.wht-rates', [
            'whtRates' => WhtRate::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        WhtRate::create($data + ['company_id' => Auth::user()->company_id, 'is_active' => $request->boolean('is_active', true)]);

        return redirect()->route('app.wht-rates.index')->with('status', __('Withholding tax category saved.'));
    }

    public function update(Request $request, WhtRate $whtRate)
    {
        $data = $this->validated($request, $whtRate);
        $whtRate->update($data + ['is_active' => $request->boolean('is_active', true)]);

        return redirect()->route('app.wht-rates.index')->with('status', __('Withholding tax category updated.'));
    }

    public function destroy(WhtRate $whtRate)
    {
        if (Bill::where('wht_rate_id', $whtRate->id)->exists()) {
            return back()->withErrors(['wht_rate' => __('This category is referenced by existing bills and cannot be deleted. Deactivate it instead.')]);
        }

        $whtRate->delete();

        return redirect()->route('app.wht-rates.index')->with('status', __('Withholding tax category deleted.'));
    }

    private function validated(Request $request, ?WhtRate $whtRate = null): array
    {
        $companyId = Auth::user()->company_id;

        return $request->validate([
            'code' => [
                'required', 'string', 'max:40',
                Rule::unique('wht_rates', 'code')->where('company_id', $companyId)->ignore($whtRate?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);
    }
}
