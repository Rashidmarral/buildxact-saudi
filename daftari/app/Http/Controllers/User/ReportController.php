<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReportController extends Controller
{
    public function vat(Request $request)
    {
        $month = $request->integer('month') ?: now()->month;
        $year = $request->integer('year') ?: now()->year;

        $invoices = Invoice::whereMonth('issue_date', $month)
            ->whereYear('issue_date', $year)
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->get();

        $expenses = Expense::whereMonth('expense_date', $month)
            ->whereYear('expense_date', $year)
            ->get();

        $outputVat = $invoices->sum('vat_total');
        $inputVat = $expenses->sum('vat_amount');

        return view('user.reports.vat', [
            'company' => Auth::user()->company,
            'month' => $month,
            'year' => $year,
            'salesTotal' => $invoices->sum('subtotal'),
            'outputVat' => $outputVat,
            'purchasesTotal' => $expenses->sum('amount'),
            'inputVat' => $inputVat,
            'netVatDue' => $outputVat - $inputVat,
            'invoiceCount' => $invoices->count(),
            'expenseCount' => $expenses->count(),
        ]);
    }
}
