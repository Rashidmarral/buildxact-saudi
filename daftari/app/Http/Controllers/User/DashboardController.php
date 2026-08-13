<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Invoice;
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
        ];

        $recentInvoices = Invoice::with('client')->latest('issue_date')->latest('id')->take(8)->get();

        return view('user.dashboard', compact('company', 'stats', 'recentInvoices'));
    }
}
