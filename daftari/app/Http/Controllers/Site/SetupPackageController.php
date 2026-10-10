<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\SetupPackage;
use App\Models\SetupPackageRequest;
use Illuminate\Http\Request;

/**
 * Public "Done-For-You" setup packages page (item 9 of the Sales,
 * Compliance & Business Growth request). Every package, its price (or
 * lack of one — see SetupPackage::priceLabel), and its feature list is
 * defined by the operator in Admin -> Setup Packages; nothing here is
 * hard-coded. Requesting a package creates a Lead the same way every
 * other public form does (see Lead::capture), so it lands in the same
 * CRM pipeline and sales-team notification as any other inbound
 * interest — plus a SetupPackageRequest that tracks fulfillment
 * separately from the sales conversation itself.
 */
class SetupPackageController extends Controller
{
    public function index()
    {
        $setupPackages = SetupPackage::where('is_active', true)->orderBy('sort_order')->get();

        return view('site.setup-packages.index', compact('setupPackages'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'setup_package_id' => ['required', 'exists:setup_packages,id'],
            'message' => ['nullable', 'string', 'max:5000'],
        ]);

        $package = SetupPackage::findOrFail($data['setup_package_id']);

        $lead = Lead::capture([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'company_name' => $data['company_name'] ?? null,
            'message' => __('Requested the ":package" setup package.', ['package' => $package->name_en]).($data['message'] ?? '' ? ' — '.$data['message'] : ''),
        ], 'setup_package');

        SetupPackageRequest::create([
            'setup_package_id' => $package->id,
            'lead_id' => $lead->id,
            'status' => 'requested',
        ]);

        return back()->with('status', __("Thanks! We've received your request and someone from our team will reach out shortly."));
    }
}
