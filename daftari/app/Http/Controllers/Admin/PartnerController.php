<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\PartnerInviteMail;
use App\Models\AuditLog;
use App\Models\Partner;
use App\Models\PartnerPayout;
use App\Models\PartnerReferral;
use App\Models\PartnerType;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Admin review/management of the Accountant Partner / Reseller / Referral
 * program (items 11-12). Approving an application is the one place a
 * Partner ever gains a login — see approve() — mirroring
 * User\TeamController::sendInvite()'s pattern (random unusable password,
 * status=invited, a signed accept link) but for Auth\PartnerInviteController
 * instead, since a partner has no Company to notify.
 */
class PartnerController extends Controller
{
    public const INVITE_EXPIRY_HOURS = 48;

    public function index(Request $request)
    {
        $query = Partner::query()->with(['partnerType', 'user']);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('partner_type_id')) {
            $query->where('partner_type_id', $request->integer('partner_type_id'));
        }
        if ($request->filled('q')) {
            $term = trim($request->q);
            $query->where(fn ($q) => $q->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%"));
        }

        $partners = $query->latest('id')->paginate(20)->withQueryString();

        $stats = [
            'pending' => Partner::where('status', 'pending')->count(),
            'active' => Partner::where('status', 'active')->count(),
            'unpaid_commission' => PartnerReferral::where('commission_status', 'approved')->sum('commission_amount'),
            'payout_requests' => PartnerPayout::where('status', 'requested')->count(),
        ];

        $partnerTypes = PartnerType::orderBy('sort_order')->get();

        return view('admin.partners.index', compact('partners', 'stats', 'partnerTypes'));
    }

    public function show(Partner $partner)
    {
        $partner->load([
            'user', 'partnerType',
            'referrals' => fn ($q) => $q->with(['lead:id,name,email,status', 'company:id,name'])->latest('id'),
            'payouts' => fn ($q) => $q->latest('id'),
        ]);

        $partnerTypes = PartnerType::where('is_active', true)->orderBy('sort_order')->get();

        return view('admin.partners.show', compact('partner', 'partnerTypes'));
    }

    public function approve(Request $request, Partner $partner)
    {
        abort_unless($partner->status === 'pending', 422);

        $data = $request->validate([
            'partner_type_id' => ['required', 'exists:partner_types,id'],
            'commission_type_override' => ['nullable', Rule::in(PartnerType::COMMISSION_TYPES)],
            'commission_value_override' => ['nullable', 'numeric', 'min:0'],
        ]);

        $member = User::create([
            'name' => $partner->name,
            'email' => $partner->email,
            'password' => Hash::make(Str::random(40)),
            'role' => 'partner',
            'status' => 'invited',
        ]);

        $partner->update([
            'user_id' => $member->id,
            'partner_type_id' => $data['partner_type_id'],
            'commission_type_override' => $data['commission_type_override'] ?? null,
            'commission_value_override' => $data['commission_value_override'] ?? null,
            'referral_code' => Partner::generateReferralCode(),
            'status' => 'invited',
            'approved_at' => now(),
        ]);

        $acceptUrl = URL::temporarySignedRoute(
            'partner.invite.accept',
            now()->addHours(self::INVITE_EXPIRY_HOURS),
            ['id' => $member->id, 'hash' => sha1($member->email)]
        );

        Mail::to($member->email)->send(new PartnerInviteMail($member, $acceptUrl));

        AuditLog::record('partner.approve', $partner, __('Approved partner application :name', ['name' => $partner->name]));

        return back()->with('status', __('Partner approved and invited.'));
    }

    public function reject(Request $request, Partner $partner)
    {
        abort_unless($partner->status === 'pending', 422);

        $data = $request->validate(['rejected_reason' => ['required', 'string', 'max:255']]);

        $partner->update(['status' => 'rejected', 'rejected_reason' => $data['rejected_reason']]);

        AuditLog::record('partner.reject', $partner, __('Rejected partner application :name', ['name' => $partner->name]));

        return back()->with('status', __('Partner application rejected.'));
    }

    public function suspend(Partner $partner)
    {
        abort_unless($partner->status === 'active', 422);

        $partner->update(['status' => 'suspended']);
        $partner->user?->update(['status' => 'suspended']);

        AuditLog::record('partner.suspend', $partner, __('Suspended partner :name', ['name' => $partner->name]));

        return back()->with('status', __('Partner suspended.'));
    }

    public function reactivate(Partner $partner)
    {
        abort_unless($partner->status === 'suspended', 422);

        $partner->update(['status' => 'active']);
        $partner->user?->update(['status' => 'active']);

        AuditLog::record('partner.reactivate', $partner, __('Reactivated partner :name', ['name' => $partner->name]));

        return back()->with('status', __('Partner reactivated.'));
    }

    public function updateCommission(Request $request, Partner $partner)
    {
        $data = $request->validate([
            'partner_type_id' => ['required', 'exists:partner_types,id'],
            'commission_type_override' => ['nullable', Rule::in(PartnerType::COMMISSION_TYPES)],
            'commission_value_override' => ['nullable', 'numeric', 'min:0'],
        ]);

        $partner->update($data);

        AuditLog::record('partner.commission_update', $partner, __('Updated commission settings for partner :name', ['name' => $partner->name]));

        return back()->with('status', __('Commission settings updated.'));
    }

    public function updateReferral(Request $request, PartnerReferral $referral)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(PartnerReferral::STATUSES)],
            'commission_amount' => ['nullable', 'numeric', 'min:0'],
            'commission_status' => ['required', Rule::in(PartnerReferral::COMMISSION_STATUSES)],
            'company_id' => ['nullable', 'exists:companies,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $referral->update($data);

        AuditLog::record('partner_referral.update', $referral, __('Updated referral for partner :name', ['name' => $referral->partner->name]));

        return back()->with('status', __('Referral updated.'));
    }

    /**
     * Marking a payout "paid" does NOT automatically flip any referral's
     * commission_status — the payout amount is a snapshot taken when the
     * partner requested it (see Partner\DashboardController::requestPayout)
     * and may not exactly match whichever referrals are "approved" by the
     * time the admin actually pays it, since more can be approved in
     * between. Reconcile which specific referrals a payout covers by
     * marking each one paid individually via updateReferral() below.
     */
    public function updatePayout(Request $request, PartnerPayout $payout)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(PartnerPayout::STATUSES)],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $data['processed_at'] = in_array($data['status'], ['paid', 'rejected'], true) ? now() : $payout->processed_at;

        $payout->update($data);

        AuditLog::record('partner_payout.update', $payout, __('Updated payout for partner :name', ['name' => $payout->partner->name]));

        return back()->with('status', __('Payout updated.'));
    }
}
