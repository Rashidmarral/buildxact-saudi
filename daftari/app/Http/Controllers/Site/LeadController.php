<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Partner;
use App\Models\PartnerReferral;
use Illuminate\Http\Request;

/**
 * The public lead-capture landing page at /get-started — the single
 * destination every "Request a demo" / "Get started" / "Check your ZATCA
 * readiness" call-to-action on the marketing site links to (the industry
 * landing pages seeded by IndustryLandingPageSeeder included), each
 * passing ?industry= and ?source= so the resulting Lead is pre-tagged
 * with where it came from. A partner's referral link (/r/{code}, see
 * Site\PartnerController::refRedirect) also lands here with ?ref=, which
 * tags the resulting Lead as source=referral and records a
 * PartnerReferral so the partner's commission can be tracked.
 */
class LeadController extends Controller
{
    public function create(Request $request)
    {
        $industry = in_array($request->query('industry'), Lead::INDUSTRIES, true) ? $request->query('industry') : null;
        $source = in_array($request->query('source'), Lead::SOURCES, true) ? $request->query('source') : 'get_started';
        $ref = $this->activePartnerForCode($request->query('ref'))?->referral_code;

        return view('site.get-started', compact('industry', 'source', 'ref'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'industry' => ['nullable', 'string', 'in:'.implode(',', Lead::INDUSTRIES)],
            'source' => ['nullable', 'string', 'in:'.implode(',', Lead::SOURCES)],
            'message' => ['nullable', 'string', 'max:5000'],
            'ref' => ['nullable', 'string', 'max:20'],
        ]);

        $partner = $this->activePartnerForCode($data['ref'] ?? null);

        $lead = Lead::capture([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'company_name' => $data['company_name'] ?? null,
            'industry' => $data['industry'] ?? null,
            'message' => $data['message'] ?? null,
        ], $partner ? 'referral' : ($data['source'] ?? 'get_started'));

        if ($partner) {
            PartnerReferral::create(['partner_id' => $partner->id, 'lead_id' => $lead->id, 'status' => 'pending']);
        }

        return back()->with('status', __("Thanks! We've received your request and someone from our team will reach out shortly."));
    }

    private function activePartnerForCode(?string $code): ?Partner
    {
        if (! $code) {
            return null;
        }

        return Partner::where('referral_code', $code)->where('status', 'active')->first();
    }
}
