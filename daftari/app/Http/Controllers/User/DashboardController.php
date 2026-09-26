<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Models\BillPayment;
use App\Models\Client;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\Item;
use App\Models\Quotation;
use App\Services\Reports\FinancialReportService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $company = Auth::user()->company;

        $invoices = Invoice::query();
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();

        $stats = [
            'total_invoiced' => (clone $invoices)->sum('total'),
            'total_outstanding' => (clone $invoices)->whereIn('status', ['sent', 'partially_paid', 'overdue'])->get()->sum(fn ($i) => $i->balanceDue()),
            'total_paid_this_month' => (clone $invoices)->whereMonth('issue_date', now()->month)->whereYear('issue_date', now()->year)->sum('amount_paid'),
            'total_expenses_this_month' => Expense::whereMonth('expense_date', now()->month)->whereYear('expense_date', now()->year)->sum('amount'),
            'total_purchases_this_month' => Bill::whereMonth('bill_date', now()->month)->whereYear('bill_date', now()->year)->sum('total'),
            'invoice_count' => (clone $invoices)->count(),
            'overdue_count' => (clone $invoices)->where('status', 'overdue')->count(),
            'open_quotations' => Quotation::whereIn('status', ['draft', 'issued'])->count(),
            // Reuses the same GL-backed indirect-method figures the Income
            // Statement report shows, rather than a rough sales-minus-
            // expenses guess — this month's operating profit, exactly as
            // an accountant would read it there.
            'profit_this_month' => app(FinancialReportService::class)->incomeStatement($company, $monthStart, $monthEnd)['netProfit'],
        ];

        $recentInvoices = Invoice::with('client')->latest('issue_date')->latest('id')->take(8)->get();

        $aging = $this->receivablesAging();
        $charts = $this->charts();

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

        return view('user.dashboard', compact('company', 'stats', 'recentInvoices', 'aging', 'checklist', 'charts'));
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

    /**
     * Every series here is fetched as plain rows and grouped in PHP
     * (Collection::groupBy) rather than a raw SQL GROUP BY — the same
     * approach receivablesAging() already uses — so this stays portable
     * across whatever database driver the deployment uses instead of
     * leaning on a driver-specific DATE()/GROUP BY dialect, and a 7-day
     * window keeps every one of these a small, cheap fetch.
     */
    private function charts(): array
    {
        $since7 = now()->subDays(6)->startOfDay();
        $since30 = now()->subDays(29)->startOfDay();
        $since90 = now()->subDays(89)->startOfDay();

        $days = collect(range(6, 0))->map(fn ($i) => now()->subDays($i)->toDateString());
        $dayLabels = $days->map(fn ($d) => Carbon::parse($d)->translatedFormat('M j'))->all();

        $invoicesByDay = Invoice::where('issue_date', '>=', $since7)->get(['issue_date', 'total'])
            ->groupBy(fn ($i) => $i->issue_date->toDateString())->map->sum('total');
        $billsByDay = Bill::where('bill_date', '>=', $since7)->get(['bill_date', 'total'])
            ->groupBy(fn ($b) => $b->bill_date->toDateString())->map->sum('total');
        $receivedByDay = InvoicePayment::where('paid_at', '>=', $since7)->get(['paid_at', 'amount'])
            ->groupBy(fn ($p) => $p->paid_at->toDateString())->map->sum('amount');
        $sentByDay = BillPayment::where('paid_at', '>=', $since7)->get(['paid_at', 'amount'])
            ->groupBy(fn ($p) => $p->paid_at->toDateString())->map->sum('amount');

        $salesPurchases = [
            'labels' => $dayLabels,
            'sales' => $days->map(fn ($d) => round((float) ($invoicesByDay[$d] ?? 0), 2))->all(),
            'purchases' => $days->map(fn ($d) => round((float) ($billsByDay[$d] ?? 0), 2))->all(),
        ];

        $paymentFlow = [
            'labels' => $dayLabels,
            'received' => $days->map(fn ($d) => round((float) ($receivedByDay[$d] ?? 0), 2))->all(),
            'sent' => $days->map(fn ($d) => round((float) ($sentByDay[$d] ?? 0), 2))->all(),
        ];

        $topItems = InvoiceItem::whereHas('invoice', fn ($q) => $q->where('issue_date', '>=', $since30))
            ->get(['item_id', 'description', 'line_total'])
            ->groupBy(fn ($line) => $line->item_id ?? 'row:'.$line->description)
            ->map(fn ($group) => ['label' => $group->first()->description, 'total' => round((float) $group->sum('line_total'), 2)])
            ->sortByDesc('total')
            ->take(5)
            ->values();

        $topCustomers = Invoice::where('issue_date', '>=', $since90)
            ->with('client:id,name')
            ->get(['client_id', 'total'])
            ->groupBy('client_id')
            ->map(fn ($group) => ['label' => $group->first()->client?->display_name ?? __('Walk-in'), 'total' => round((float) $group->sum('total'), 2)])
            ->sortByDesc('total')
            ->take(5)
            ->values();

        $paymentMethods = InvoicePayment::where('paid_at', '>=', $since90)
            ->get(['method', 'amount'])
            ->groupBy(fn ($p) => $p->method ?: 'other')
            ->map(fn ($group) => (float) $group->sum('amount'))
            ->sortByDesc(fn ($amount) => $amount);

        return [
            'salesPurchases' => $salesPurchases,
            'paymentFlow' => $paymentFlow,
            'topItems' => $topItems,
            'topCustomers' => $topCustomers,
            'paymentMethods' => $paymentMethods,
        ];
    }
}
