<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BranchController extends Controller
{
    public function index()
    {
        $branches = Branch::orderBy('name')->get();

        return view('user.branches.index', compact('branches'));
    }

    public function create()
    {
        return view('user.branches.form', ['branch' => new Branch]);
    }

    public function store(Request $request)
    {
        if (Auth::user()->company->hasReachedPlanLimit('branches')) {
            return back()->withErrors(['plan_limit' => __('You have reached your plan\'s branch limit. Upgrade your plan to add more branches.')])->withInput();
        }

        $data = $this->validated($request);
        $branch = Branch::create($data);

        if (! Auth::user()->company->default_branch_id) {
            Auth::user()->company->update(['default_branch_id' => $branch->id]);
        }

        return redirect()->route('app.branches.index')->with('status', __('Branch saved.'));
    }

    public function edit(Branch $branch)
    {
        return view('user.branches.form', compact('branch'));
    }

    public function update(Request $request, Branch $branch)
    {
        $branch->update($this->validated($request));

        return redirect()->route('app.branches.index')->with('status', __('Branch updated.'));
    }

    public function destroy(Branch $branch)
    {
        $company = Auth::user()->company;
        if ($company->default_branch_id === $branch->id) {
            $company->update(['default_branch_id' => null]);
        }
        $branch->delete();

        return redirect()->route('app.branches.index')->with('status', __('Branch deleted.'));
    }

    public function makeDefault(Branch $branch)
    {
        Auth::user()->company->update(['default_branch_id' => $branch->id]);

        return back()->with('status', __('Default branch updated.'));
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'cr_number' => ['nullable', 'string', 'max:32'],
            'street_name' => ['nullable', 'string', 'max:255'],
            'building_number' => ['nullable', 'string', 'max:20'],
            'district' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);
    }
}
