<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function index(Request $request)
    {
        $companies = Company::withCount(['users', 'invoices'])
            ->when($request->q, fn ($q, $term) => $q->where('name', 'like', "%{$term}%"))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.companies.index', compact('companies'));
    }

    public function show(Company $company)
    {
        $company->load(['users', 'subscriptions.plan', 'payments' => fn ($q) => $q->latest('paid_at')->take(10)]);

        return view('admin.companies.show', compact('company'));
    }

    public function suspend(Company $company)
    {
        $company->update(['status' => 'suspended']);

        return back()->with('status', __('Company suspended.'));
    }

    public function activate(Company $company)
    {
        $company->update(['status' => 'active']);

        return back()->with('status', __('Company activated.'));
    }
}
