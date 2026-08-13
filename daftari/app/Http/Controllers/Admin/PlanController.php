<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PlanController extends Controller
{
    public function index()
    {
        $plans = Plan::orderBy('sort_order')->get();

        return view('admin.plans.index', compact('plans'));
    }

    public function create()
    {
        return view('admin.plans.form', ['plan' => new Plan]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['slug'] = Str::slug($data['name']);
        Plan::create($data);

        return redirect()->route('admin.plans.index')->with('status', __('Plan created.'));
    }

    public function edit(Plan $plan)
    {
        return view('admin.plans.form', compact('plan'));
    }

    public function update(Request $request, Plan $plan)
    {
        $data = $this->validated($request);
        $plan->update($data);

        return redirect()->route('admin.plans.index')->with('status', __('Plan updated.'));
    }

    public function destroy(Plan $plan)
    {
        if ($plan->subscriptions()->exists()) {
            return back()->withErrors(['plan' => __('This plan has active subscribers and cannot be deleted. Deactivate it instead.')]);
        }

        $plan->delete();

        return redirect()->route('admin.plans.index')->with('status', __('Plan deleted.'));
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'price_monthly' => ['required', 'numeric', 'min:0'],
            'price_yearly' => ['required', 'numeric', 'min:0'],
            'max_users' => ['nullable', 'integer', 'min:1'],
            'max_invoices_per_month' => ['nullable', 'integer', 'min:1'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'features' => ['nullable', 'string'],
        ]);

        $data['features'] = $data['features']
            ? array_values(array_filter(array_map('trim', explode("\n", $data['features']))))
            : [];
        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }
}
