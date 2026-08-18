<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\ZatcaCreditNoteLog;
use App\Models\ZatcaInvoiceLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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

        $plans = Plan::where('is_active', true)->orderBy('sort_order')->get();
        $subscription = $company->activeSubscription();

        $usage = null;
        if ($subscription) {
            $usage = [
                'invoices' => ['used' => $company->invoices()->count(), 'limit' => $subscription->plan->max_invoices_per_month],
                'customers' => ['used' => $company->clients()->count(), 'limit' => $subscription->plan->max_customers],
                'suppliers' => ['used' => $company->suppliers()->count(), 'limit' => $subscription->plan->max_suppliers],
                'users' => ['used' => $company->users()->count(), 'limit' => $subscription->plan->max_users],
            ];
        }

        $zatca = [
            'cleared' => ZatcaInvoiceLog::withoutGlobalScopes()->where('company_id', $company->id)->where('status', 'cleared')->count()
                + ZatcaCreditNoteLog::withoutGlobalScopes()->where('company_id', $company->id)->where('status', 'cleared')->count(),
            'reported' => ZatcaInvoiceLog::withoutGlobalScopes()->where('company_id', $company->id)->where('status', 'reported')->count()
                + ZatcaCreditNoteLog::withoutGlobalScopes()->where('company_id', $company->id)->where('status', 'reported')->count(),
            'failed' => ZatcaInvoiceLog::withoutGlobalScopes()->where('company_id', $company->id)->where('status', 'failed')->count()
                + ZatcaCreditNoteLog::withoutGlobalScopes()->where('company_id', $company->id)->where('status', 'failed')->count(),
        ];

        $auditLogs = AuditLog::with('admin')
            ->where('subject_type', Company::class)
            ->where('subject_id', $company->id)
            ->latest('created_at')
            ->take(15)
            ->get();

        return view('admin.companies.show', compact('company', 'plans', 'subscription', 'usage', 'zatca', 'auditLogs'));
    }

    public function suspend(Company $company)
    {
        $company->update(['status' => 'suspended']);
        AuditLog::record('company.suspend', $company, __('Suspended :name', ['name' => $company->name]));

        return back()->with('status', __('Company suspended.'));
    }

    public function activate(Company $company)
    {
        $company->update(['status' => 'active']);
        AuditLog::record('company.activate', $company, __('Activated :name', ['name' => $company->name]));

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

        DB::transaction(function () use ($company, $plan, $data, $subscription) {
            if ($subscription) {
                $subscription->update([
                    'plan_id' => $plan->id,
                    'billing_cycle' => $data['billing_cycle'],
                    'status' => $data['status'],
                    'current_period_end' => $data['current_period_end'],
                ]);
            } else {
                $subscription = Subscription::create([
                    'company_id' => $company->id,
                    'plan_id' => $plan->id,
                    'status' => $data['status'],
                    'billing_cycle' => $data['billing_cycle'],
                    'current_period_start' => now(),
                    'current_period_end' => $data['current_period_end'],
                ]);
            }

            AuditLog::record(
                'company.change_plan',
                $company,
                __('Set :name to :plan (:status)', ['name' => $company->name, 'plan' => $plan->name, 'status' => $data['status']])
            );
        });

        return back()->with('status', __('Subscription updated.'));
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
