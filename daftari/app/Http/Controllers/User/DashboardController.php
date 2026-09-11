<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Quotation;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $company = Auth::user()->company;

        $invoices = Invoice::query();

        $stats = [
            'total_invoiced' => (clone $invoices)->sum('total'),
            'total_outstanding' => (clone $invoices)->whereIn('status', ['sent', 'partially_paid', 'overdue'])->get()->sum(fn ($i) => $i->balanceDue()),
            'total_paid_this_month' => (clone $invoices)->whereMonth('issue_date', now()->month)->whereYear('issue_date', now()->year)->sum('amount_paid'),
            'total_expenses_this_month' => Expense::whereMonth('expense_date', now()->month)->whereYear('expense_date', now()->year)->sum('amount'),
            'invoice_count' => (clone $invoices)->count(),
            'overdue_count' => (clone $invoices)->where('status', 'overdue')->count(),
            'open_quotations' => Quotation::whereIn('status', ['draft', 'issued'])->count(),
        ];

        $recentInvoices = Invoice::with('client')->latest('issue_date')->latest('id')->take(8)->get();

        $aging = $this->receivablesAging();

        $checklist = [
            ['label' => __('Add your company logo'), 'done' => (bool) $company->logo_path, 'route' => 'app.settings.index'],
            ['label' => __('Add your VAT number'), 'done' => (bool) $company->vat_number, 'route' => 'app.settings.index'],
            ['label' => __('Add a client'), 'done' => Client::exists(), 'route' => 'app.clients.create'],
            ['label' => __('Add an item or service'), 'done' => Item::exists(), 'route' => 'app.items.create'],
            ['label' => __('Create your first invoice'), 'done' => Invoice::exists(), 'route' => 'app.invoices.create'],
            ['label' => __('Record your first expense'), 'done' => Expense::exists(), 'route' => 'app.expenses.create'],
        ];

        // Audit finding LOW-32: ZATCA e-invoicing compliance is central
        // to this product, yet the onboarding checklist never mentioned
        // it — a company could tick off everything above and still be
        // issuing invoices with no CSID, no clearance, no compliance at
        // all. Only shown when the plan actually includes ZATCA Phase 2;
        // isZatcaOnboarded() would otherwise sit permanently unchecked
        // for a company whose plan doesn't carry the feature at all.
        if ($company->hasFeature('zatca_phase2')) {
            $checklist[] = ['label' => __('Complete ZATCA e-invoicing setup'), 'done' => $company->isZatcaOnboarded(), 'route' => 'app.zatca.dashboard'];
        }

        return view('user.dashboard', compact('company', 'stats', 'recentInvoices', 'aging', 'checklist'));
    }

    private function receivablesAging(): array
    {
        $outstanding = Invoice::with('client')
            ->whereIn('status', ['sent', 'partially_paid', 'overdue'])
            ->get();

        $buckets = ['current' => 0.0, '1_30' => 0.0, '31_60' => 0.0, '61_plus' => 0.0];

        foreach ($outstanding as $invoice) {
            $balance = $invoice->balanceDue();
            if ($balance <= 0) {
                continue;
            }

            $daysOverdue = $invoice->due_date ? now()->diffInDays($invoice->due_date, false) * -1 : -1;

            if ($daysOverdue <= 0) {
                $buckets['current'] += $balance;
            } elseif ($daysOverdue <= 30) {
                $buckets['1_30'] += $balance;
            } elseif ($daysOverdue <= 60) {
                $buckets['31_60'] += $balance;
            } else {
                $buckets['61_plus'] += $balance;
            }
        }

        return $buckets;
    }
}
