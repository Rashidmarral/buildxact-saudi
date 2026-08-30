<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use App\Models\ZatcaCreditNoteLog;
use App\Models\ZatcaInvoiceLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CompanyController extends Controller
{
    /**
     * "Status" filter values that aren't a literal companies.status or
     * subscriptions.status value — these read the latest-subscription
     * snapshot computed below instead.
     */
    private const SUBSCRIPTION_STATUS_FILTERS = ['trial' => 'trialing', 'expired' => 'expired', 'cancelled' => 'cancelled', 'paid' => 'active'];

    public function index(Request $request)
    {
        // One row per company — its most recent subscription — reused for
        // the Plan column, the status filters below, and the Subscription
        // status column. Two queries total, not N+1 (same pattern as the
        // platform dashboard's KPIs).
        $latestSubscriptionIds = Subscription::withoutGlobalScopes()
            ->selectRaw('MAX(id) as id')
            ->groupBy('company_id');
        $latestSubscriptions = Subscription::withoutGlobalScopes()
            ->whereIn('id', $latestSubscriptionIds)
            ->with('plan:id,name,name_ar')
            ->get()
            ->keyBy('company_id');

        $query = Company::withCount(['users', 'branches']);

        if ($request->filled('q')) {
            $term = trim($request->q);
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%")
                    ->orWhere('vat_number', 'like', "%{$term}%")
                    ->orWhere('cr_number', 'like', "%{$term}%")
                    ->orWhereHas('users', function ($uq) use ($term) {
                        $uq->where('role', 'owner')->where(function ($uq2) use ($term) {
                            $uq2->where('name', 'like', "%{$term}%")
                                ->orWhere('email', 'like', "%{$term}%")
                                ->orWhere('phone', 'like', "%{$term}%");
                        });
                    });
            });
        }

        if ($request->filled('plan_id')) {
            $query->whereIn('id', $latestSubscriptions->where('plan_id', (int) $request->plan_id)->keys());
        }

        if ($request->filled('status')) {
            $status = $request->string('status')->toString();

            if ($status === 'active') {
                $query->where('status', 'active');
            } elseif ($status === 'suspended') {
                $query->where('status', 'suspended');
            } elseif ($status === 'past_due') {
                $ids = $latestSubscriptions->filter(fn ($s) => $s->status === 'active'
                    && ! $s->cancelled_at
                    && $s->current_period_end
                    && $s->current_period_end->isPast())->keys();
                $query->whereIn('id', $ids);
            } elseif (array_key_exists($status, self::SUBSCRIPTION_STATUS_FILTERS)) {
                $ids = $latestSubscriptions->where('status', self::SUBSCRIPTION_STATUS_FILTERS[$status])->keys();
                $query->whereIn('id', $ids);
            }
        }

        if ($request->filled('zatca')) {
            $query->where('zatca_onboarding_status', $request->zatca === 'connected' ? 'onboarded' : 'failed');
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $companies = $query->latest()->paginate(20)->withQueryString();

        $pageIds = $companies->pluck('id');

        $owners = User::withoutGlobalScopes()
            ->whereIn('company_id', $pageIds)
            ->where('role', 'owner')
            ->orderBy('id')
            ->get(['id', 'company_id', 'name', 'email', 'phone'])
            ->groupBy('company_id')
            ->map(fn ($group) => $group->first());

        $storageByCompany = Attachment::withoutGlobalScopes()
            ->whereIn('company_id', $pageIds)
            ->selectRaw('company_id, SUM(size) as total')
            ->groupBy('company_id')
            ->pluck('total', 'company_id');

        $lastLoginByCompany = DB::table('sessions')
            ->join('users', 'users.id', '=', 'sessions.user_id')
            ->whereIn('users.company_id', $pageIds)
            ->selectRaw('users.company_id, MAX(sessions.last_activity) as last_activity')
            ->groupBy('users.company_id')
            ->pluck('last_activity', 'company_id');

        $plans = Plan::orderBy('sort_order')->get(['id', 'name', 'name_ar']);

        return view('admin.companies.index', compact(
            'companies', 'latestSubscriptions', 'owners', 'storageByCompany', 'lastLoginByCompany', 'plans'
        ));
    }

    public function show(Company $company)
    {
        $company->load(['users' => fn ($q) => $q->orderBy('name'), 'subscriptions.plan', 'payments' => fn ($q) => $q->latest('paid_at')->take(20)]);

        $plans = Plan::where('is_active', true)->orderBy('sort_order')->get();
        $subscription = $company->activeSubscription();

        $usage = null;
        if ($subscription) {
            $usage = [
                'invoices' => ['used' => $company->invoices()->count(), 'limit' => $subscription->plan->max_invoices_per_month],
                'customers' => ['used' => $company->clients()->count(), 'limit' => $subscription->plan->max_customers],
                'suppliers' => ['used' => $company->suppliers()->count(), 'limit' => $subscription->plan->max_suppliers],
                'users' => ['used' => $company->users()->count(), 'limit' => $subscription->plan->max_users],
                'invoice_templates' => ['used' => $company->invoiceTemplates()->count(), 'limit' => $subscription->plan->max_invoice_templates],
                'warehouses' => ['used' => $company->warehouses()->count(), 'limit' => $subscription->plan->max_warehouses],
                'bank_accounts' => ['used' => $company->bankAccounts()->count(), 'limit' => $subscription->plan->max_bank_accounts],
                'branches' => ['used' => $company->branches()->count(), 'limit' => $subscription->plan->max_branches],
            ];
        }

        $zatca = [
            'cleared' => ZatcaInvoiceLog::withoutGlobalScopes()->where('company_id', $company->id)->where('status', 'cleared')->count()
                + ZatcaCreditNoteLog::withoutGlobalScopes()->where('company_id', $company->id)->where('status', 'cleared')->count(),
            'reported' => ZatcaInvoiceLog::withoutGlobalScopes()->where('company_id', $company->id)->where('status', 'reported')->count()
                + ZatcaCreditNoteLog::withoutGlobalScopes()->where('company_id', $company->id)->where('status', 'reported')->count(),
            'failed' => ZatcaInvoiceLog::withoutGlobalScopes()->where('company_id', $company->id)->where('status', 'failed')->count()
                + ZatcaCreditNoteLog::withoutGlobalScopes()->where('company_id', $company->id)->where('status', 'failed')->count(),
            'last_submission' => max(
                ZatcaInvoiceLog::withoutGlobalScopes()->where('company_id', $company->id)->max('created_at'),
                ZatcaCreditNoteLog::withoutGlobalScopes()->where('company_id', $company->id)->max('created_at'),
            ),
            'certificate_status' => $company->zatca_production_csid
                ? 'production'
                : ($company->zatca_compliance_csid ? 'compliance_only' : 'not_issued'),
        ];

        $failedZatcaSubmissions = ZatcaInvoiceLog::withoutGlobalScopes()->where('company_id', $company->id)->where('status', 'failed')
            ->latest('created_at')->take(10)->get();

        $branches = Branch::withoutGlobalScopes()->where('company_id', $company->id)->orderBy('name')->get();

        $roles = Role::withoutGlobalScopes()->where('company_id', $company->id)->withCount('users')->orderBy('name')->get();

        $activeUsersCount = $company->users->where('status', 'active')->count();
        $inactiveUsersCount = $company->users->count() - $activeUsersCount;

        $failedPayments = $company->payments->where('status', 'failed')->values();

        $storageUsedBytes = $company->storageUsedBytes();

        $auditLogs = AuditLog::with('admin')
            ->where(function ($q) use ($company) {
                $q->where('company_id', $company->id)
                    ->orWhere(function ($q2) use ($company) {
                        $q2->where('subject_type', Company::class)->where('subject_id', $company->id);
                    });
            })
            ->latest('created_at')
            ->take(30)
            ->get();

        return view('admin.companies.show', compact(
            'company', 'plans', 'subscription', 'usage', 'zatca', 'failedZatcaSubmissions',
            'branches', 'roles', 'activeUsersCount', 'inactiveUsersCount', 'failedPayments',
            'storageUsedBytes', 'auditLogs'
        ));
    }

    public function suspend(Company $company)
    {
        $old = ['status' => $company->status];
        $company->update(['status' => 'suspended']);
        AuditLog::record('company.suspend', $company, __('Suspended :name', ['name' => $company->name]), old: $old, new: ['status' => 'suspended']);

        return back()->with('status', __('Company suspended.'));
    }

    public function activate(Company $company)
    {
        $old = ['status' => $company->status];
        $company->update(['status' => 'active']);
        AuditLog::record('company.activate', $company, __('Activated :name', ['name' => $company->name]), old: $old, new: ['status' => 'active']);

        return back()->with('status', __('Company activated.'));
    }

    /**
     * Changes (or creates) the company's subscription directly — the same
     * DB shape BillingController::upgrade() produces for a real self-serve
     * upgrade, minus the payment record: this is a support/ops override,
     * not a purchase, so nothing here pretends money changed hands.
     */
    public function changePlan(Request $request, Company $company)
    {
        $data = $request->validate([
            'plan_id' => ['required', 'exists:plans,id'],
            'billing_cycle' => ['required', 'in:monthly,yearly'],
            'status' => ['required', 'in:trialing,active,cancelled,expired'],
            'current_period_end' => ['required', 'date'],
        ]);

        $plan = Plan::findOrFail($data['plan_id']);
        $subscription = $company->activeSubscription();

        $old = $subscription ? $subscription->only(['plan_id', 'billing_cycle', 'status', 'current_period_end']) : null;

        // Keep cancelled_at consistent with status here too, since it's the
        // same column the self-service cancel flow and the admin dashboard's
        // "cancellations this month" metric both rely on.
        $cancelledAt = $data['status'] === 'cancelled' ? ($subscription?->cancelled_at ?? now()) : null;

        DB::transaction(function () use ($company, $plan, $data, $subscription, $cancelledAt, $old) {
            if ($subscription) {
                $subscription->update([
                    'plan_id' => $plan->id,
                    'billing_cycle' => $data['billing_cycle'],
                    'status' => $data['status'],
                    'current_period_end' => $data['current_period_end'],
                    'cancelled_at' => $cancelledAt,
                ]);
            } else {
                $subscription = Subscription::create([
                    'company_id' => $company->id,
                    'plan_id' => $plan->id,
                    'status' => $data['status'],
                    'billing_cycle' => $data['billing_cycle'],
                    'current_period_start' => now(),
                    'current_period_end' => $data['current_period_end'],
                    'cancelled_at' => $cancelledAt,
                ]);
            }

            AuditLog::record(
                'company.change_plan',
                $company,
                __('Set :name to :plan (:status)', ['name' => $company->name, 'plan' => $plan->name, 'status' => $data['status']]),
                old: $old,
                new: $subscription->only(['plan_id', 'billing_cycle', 'status', 'current_period_end'])
            );
        });

        return back()->with('status', __('Subscription updated.'));
    }

    /**
     * Pushes the company's trial (and, if it's still trialing, its current
     * subscription period) out by N days. Days beyond the original trial
     * length are fine here — this is a manual support override, not the
     * self-service signup flow.
     */
    public function extendTrial(Request $request, Company $company)
    {
        $data = $request->validate([
            'days' => ['required', 'integer', 'min:1', 'max:365'],
        ]);

        $subscription = $company->activeSubscription();
        $old = [
            'trial_ends_at' => optional($company->trial_ends_at)->toIso8601String(),
            'subscription_current_period_end' => $subscription ? optional($subscription->current_period_end)->toIso8601String() : null,
        ];

        $base = $company->trial_ends_at && $company->trial_ends_at->isFuture() ? $company->trial_ends_at : now();
        $newTrialEndsAt = $base->copy()->addDays((int) $data['days']);

        DB::transaction(function () use ($company, $subscription, $newTrialEndsAt) {
            $company->update(['trial_ends_at' => $newTrialEndsAt]);

            if ($subscription && $subscription->status === 'trialing') {
                $subscription->update(['current_period_end' => $newTrialEndsAt]);
            }
        });

        AuditLog::record(
            'company.extend_trial',
            $company,
            __('Extended trial for :name by :days day(s)', ['name' => $company->name, 'days' => $data['days']]),
            old: $old,
            new: [
                'trial_ends_at' => $newTrialEndsAt->toIso8601String(),
                'subscription_current_period_end' => $subscription && $subscription->status === 'trialing' ? $newTrialEndsAt->toIso8601String() : ($old['subscription_current_period_end'] ?? null),
            ]
        );

        return back()->with('status', __('Trial extended.'));
    }

    /**
     * Admin override of the self-service "cancel at period end" flow in
     * BillingController::cancel(): sets the same cancelled_at column, so the
     * existing subscriptions:expire-cancelled scheduled command still does
     * the actual status flip — no separate cancellation state invented.
     */
    public function cancelSubscription(Company $company)
    {
        $subscription = $company->activeSubscription();
        abort_unless($subscription && ! $subscription->cancelled_at, 404);

        $old = ['cancelled_at' => null, 'status' => $subscription->status];
        $subscription->update(['cancelled_at' => now()]);

        AuditLog::record(
            'company.cancel_subscription',
            $company,
            __('Cancelled subscription for :name', ['name' => $company->name]),
            old: $old,
            new: ['cancelled_at' => $subscription->cancelled_at->toIso8601String(), 'status' => $subscription->status]
        );

        return back()->with('status', __('Subscription scheduled for cancellation.'));
    }

    /**
     * Admin override of the self-service resume flow in
     * BillingController::resume(): clears cancelled_at, undoing a pending
     * (not-yet-expired) cancellation — mirrors the same self-service action.
     */
    public function resumeSubscription(Company $company)
    {
        $subscription = $company->activeSubscription();
        abort_unless($subscription && $subscription->cancelled_at, 404);

        $old = ['cancelled_at' => $subscription->cancelled_at->toIso8601String()];
        $subscription->update(['cancelled_at' => null]);

        AuditLog::record(
            'company.resume_subscription',
            $company,
            __('Resumed subscription for :name', ['name' => $company->name]),
            old: $old,
            new: ['cancelled_at' => null]
        );

        return back()->with('status', __('Subscription resumed.'));
    }

    /**
     * Resets operational settings (numbering, locale/currency, approval
     * thresholds, ZATCA sync behavior) back to platform defaults. Never
     * touches users, financial records, ZATCA credentials/onboarding state,
     * VAT/CR numbers, or address — those are not "settings", and resetting
     * them would be destructive far beyond what "reset settings" implies.
     */
    public function resetSettings(Company $company)
    {
        $defaults = [
            'currency' => 'SAR', 'locale' => 'en', 'timezone' => config('app.timezone'),
            'primary_customer_type' => 'mixed', 'negative_number_format' => 'minus',
            'po_approval_threshold' => null, 'expense_approval_threshold' => null,
            'invoice_prefix' => 'INV', 'next_invoice_number' => 1,
            'credit_note_prefix' => 'CN', 'next_credit_note_number' => 1,
            'purchase_return_prefix' => 'PR', 'next_purchase_return_number' => 1,
            'quotation_prefix' => 'QTN', 'next_quotation_number' => 1,
            'proforma_prefix' => 'PRO', 'next_proforma_number' => 1,
            'receipt_prefix' => 'RV', 'next_receipt_number' => 1,
            'payment_voucher_prefix' => 'PV', 'next_payment_voucher_number' => 1,
            'bill_prefix' => 'BILL', 'next_bill_number' => 1,
            'po_prefix' => 'PO', 'next_po_number' => 1,
            'journal_prefix' => 'JE', 'next_journal_number' => 1,
            'project_prefix' => 'PROJ', 'next_project_number' => 1,
            'zatca_sync_frequency' => 'manual', 'zatca_sync_b2b' => true, 'zatca_sync_b2c' => true,
        ];

        $old = $company->only(array_keys($defaults));

        $company->forceFill($defaults)->save();

        AuditLog::record(
            'company.reset_settings',
            $company,
            __('Reset settings for :name to platform defaults', ['name' => $company->name]),
            old: $old,
            new: $defaults
        );

        return back()->with('status', __('Company settings reset to defaults.'));
    }

    /**
     * Support impersonation: logs the admin into the company's owner
     * account so support can see exactly what the customer sees. The
     * admin's own id is stashed in the session (not lost) so
     * ImpersonationController::stop() can restore it — impersonation never
     * creates a second concurrent session or touches the owner's password.
     */
    public function impersonate(Company $company)
    {
        $target = $company->users()->where('role', 'owner')->first() ?? $company->users()->first();

        if (! $target) {
            return back()->withErrors(['company' => __('This company has no users to impersonate.')]);
        }

        session(['impersonator_id' => Auth::id()]);
        AuditLog::record('company.impersonate', $company, __('Started impersonating :name as :user', ['name' => $company->name, 'user' => $target->email]));

        Auth::login($target);

        return redirect()->route('app.dashboard');
    }
}
