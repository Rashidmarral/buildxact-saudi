<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Mail\NewPartnerApplicationMail;
use App\Models\Partner;
use App\Models\PartnerType;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

/**
 * Public entry point for the Accountant Partner / Reseller / Referral
 * program (items 11-12 of the Sales, Compliance & Business Growth
 * request). Applying here never grants a login — it only creates a
 * pending Partner row an admin reviews at Admin -> Partner Program; a
 * login is created only on approval (see Admin\PartnerController::approve
 * and Auth\PartnerInviteController).
 */
class PartnerController extends Controller
{
    public function apply()
    {
        $partnerTypes = PartnerType::where('is_active', true)->orderBy('sort_order')->get();

        return view('site.partners.apply', compact('partnerTypes'));
    }

    public function submitApply(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'partner_type_id' => ['nullable', 'exists:partner_types,id'],
            'message' => ['nullable', 'string', 'max:5000'],
        ]);

        $partner = Partner::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'company_name' => $data['company_name'] ?? null,
            'partner_type_id' => $data['partner_type_id'] ?? null,
            'message' => $data['message'] ?? null,
            'status' => 'pending',
        ]);

        $notifyEmail = Setting::get('support_email', config('mail.from.address'));
        if ($notifyEmail) {
            Mail::to($notifyEmail)->send(new NewPartnerApplicationMail($partner));
        }

        return back()->with('status', __("Thanks for applying! We'll review your application and get back to you shortly."));
    }

    /**
     * A partner's referral link, shared with prospects. Redirects into
     * the same /get-started funnel as every other lead source, tagged so
     * the resulting Lead and commission trail can be tracked (see
     * Site\LeadController). Silently falls back to /get-started with no
     * referral tag for an unknown or inactive code rather than a 404,
     * since this URL is meant to be shared publicly.
     */
    public function refRedirect(string $code)
    {
        $partner = Partner::where('referral_code', $code)->where('status', 'active')->first();

        return redirect()->route('get-started', $partner ? ['ref' => $partner->referral_code] : []);
    }
}
