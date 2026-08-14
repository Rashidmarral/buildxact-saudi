<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\CostCenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class CostCenterController extends Controller
{
    public function index()
    {
        $costCenters = Auth::user()->company->costCenters()->orderBy('code')->get();

        return view('user.cost-centers.index', compact('costCenters'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        Auth::user()->company->costCenters()->create($data);

        return back()->with('status', __('Cost center added.'));
    }

    public function update(Request $request, CostCenter $costCenter)
    {
        $costCenter->update($this->validated($request, $costCenter->id));

        return back()->with('status', __('Cost center updated.'));
    }

    public function destroy(CostCenter $costCenter)
    {
        if ($costCenter->journalEntryLines()->exists()) {
            return back()->withErrors(['cost_center' => __('This cost center has journal entries and cannot be deleted. Deactivate it instead.')]);
        }

        $costCenter->delete();

        return back()->with('status', __('Cost center deleted.'));
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $companyId = Auth::user()->company_id;

        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('cost_centers')->where('company_id', $companyId)->ignore($ignoreId)],
            'name' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
        ]);

        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }
}
