<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CouponController extends Controller
{
    public function index(Request $request)
    {
        $query = Coupon::withCount('redemptions')->withSum('redemptions', 'discount_amount');

        if ($request->filled('q')) {
            $query->where('code', 'like', '%'.strtoupper(trim($request->q)).'%');
        }

        if ($request->filled('status')) {
            match ($request->status) {
                'active' => $query->where('is_active', true)->where(function ($q) {
                    $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                }),
                'inactive' => $query->where('is_active', false),
                'expired' => $query->whereNotNull('expires_at')->where('expires_at', '<=', now()),
                default => null,
            };
        }

        $coupons = $query->latest()->paginate(20)->withQueryString();

        return view('admin.coupons.index', compact('coupons'));
    }

    public function create()
    {
        return view('admin.coupons.form', ['coupon' => new Coupon, 'plans' => Plan::orderBy('sort_order')->get()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $coupon = DB::transaction(function () use ($data) {
            $coupon = Coupon::create($data);
            $coupon->plans()->sync($data['plan_ids'] ?? []);

            return $coupon;
        });

        AuditLog::record('coupon.create', $coupon, __('Created coupon :code', ['code' => $coupon->code]), new: $coupon->only(['code', 'discount_type', 'percentage', 'fixed_amount']));

        return redirect()->route('admin.coupons.index')->with('status', __('Coupon created.'));
    }

    public function show(Coupon $coupon)
    {
        $coupon->loadCount('redemptions')->load(['plans', 'redemptions' => fn ($q) => $q->with(['company', 'payment'])->latest('redeemed_at')->take(50)]);

        $stats = [
            'total_uses' => $coupon->redemptions()->count(),
            'remaining_uses' => $coupon->remainingUses(),
            'revenue_discounted' => $coupon->redemptions()->sum('discount_amount'),
            'companies_using' => $coupon->redemptions()->distinct('company_id')->count('company_id'),
        ];

        $auditLogs = AuditLog::with('admin')
            ->where('subject_type', Coupon::class)
            ->where('subject_id', $coupon->id)
            ->latest('created_at')
            ->take(30)
            ->get();

        return view('admin.coupons.show', compact('coupon', 'stats', 'auditLogs'));
    }

    public function edit(Coupon $coupon)
    {
        return view('admin.coupons.form', ['coupon' => $coupon, 'plans' => Plan::orderBy('sort_order')->get()]);
    }

    public function update(Request $request, Coupon $coupon)
    {
        $data = $this->validated($request, $coupon);
        $old = $coupon->only(['discount_type', 'percentage', 'fixed_amount', 'is_active', 'max_uses', 'max_uses_per_company']);

        DB::transaction(function () use ($coupon, $data) {
            $coupon->update($data);
            $coupon->plans()->sync($data['plan_ids'] ?? []);
        });

        AuditLog::record('coupon.update', $coupon, __('Updated coupon :code', ['code' => $coupon->code]), old: $old, new: $coupon->only(array_keys($old)));

        return redirect()->route('admin.coupons.index')->with('status', __('Coupon updated.'));
    }

    public function destroy(Coupon $coupon)
    {
        if ($coupon->redemptions()->exists()) {
            return back()->withErrors(['coupon' => __('This coupon has already been used and cannot be deleted. Deactivate it instead.')]);
        }

        AuditLog::record('coupon.delete', $coupon, __('Deleted coupon :code', ['code' => $coupon->code]));

        $coupon->delete();

        return redirect()->route('admin.coupons.index')->with('status', __('Coupon deleted.'));
    }

    private function validated(Request $request, ?Coupon $coupon = null): array
    {
        // Normalized before the uniqueness check runs (not just in the
        // model's saving() hook) so "save20" can't slip past a uniqueness
        // check against an existing "SAVE20" on a case-sensitive collation.
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('coupons', 'code')->ignore($coupon?->id)],
            'description' => ['nullable', 'string', 'max:1000'],
            'discount_type' => ['required', Rule::in(Coupon::DISCOUNT_TYPES)],
            'percentage' => ['nullable', 'numeric', 'min:0.01', 'max:100', 'required_without:fixed_amount', 'prohibits:fixed_amount'],
            'fixed_amount' => ['nullable', 'numeric', 'min:0.01', 'required_without:percentage'],
            'currency' => ['required', 'string', 'size:3'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'max_uses_per_company' => ['nullable', 'integer', 'min:1'],
            'min_subscription_amount' => ['nullable', 'numeric', 'min:0'],
            'duration_in_cycles' => ['required_if:discount_type,limited_duration', 'nullable', 'integer', 'min:1'],
            'plan_ids' => ['nullable', 'array'],
            'plan_ids.*' => ['integer', 'exists:plans,id'],
        ]);

        $data['new_customers_only'] = $request->boolean('new_customers_only');
        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }
}
