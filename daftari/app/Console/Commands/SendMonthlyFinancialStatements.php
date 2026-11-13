<?php

namespace App\Console\Commands;

use App\Mail\MonthlyFinancialStatementsMail;
use App\Models\Company;
use App\Models\User;
use App\Services\MpdfRenderer;
use App\Services\Reports\FinancialReportService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

/**
 * "add more... Auto-emailed monthly financial statements" — the last
 * item of Batch D. For every company that's opted in (Settings >
 * Approvals > "Monthly financial statements"), emails a Balance Sheet
 * (as of last month's end) and an Income Statement (for last month) as
 * PDF attachments to every user holding the 'reports' permission — the
 * same recipient rule SendCompanyDocumentExpiryReminders already uses
 * for its own notifications, so who gets these isn't a new concept to
 * configure. financial_statements_last_sent_period guards against
 * sending twice for the same month if this command is ever run more
 * than once in it (the schedule itself already has withoutOverlapping(),
 * which only prevents a *concurrent* second run, not a later manual one).
 */
class SendMonthlyFinancialStatements extends Command
{
    protected $signature = 'financial-statements:send-monthly';

    protected $description = 'Email the prior month\'s Balance Sheet and Income Statement to every opted-in company\'s report viewers';

    public function handle(): int
    {
        $periodStart = now()->subMonthNoOverflow()->startOfMonth();
        $periodEnd = now()->subMonthNoOverflow()->endOfMonth();
        $period = $periodStart->format('Y-m');

        $companies = Company::where('financial_statements_auto_email_enabled', true)
            ->where('status', 'active')
            ->where(fn ($q) => $q->whereNull('financial_statements_last_sent_period')->orWhere('financial_statements_last_sent_period', '!=', $period))
            ->get();

        $sent = 0;

        foreach ($companies as $company) {
            if ($this->sendFor($company, $periodStart, $periodEnd)) {
                $company->update(['financial_statements_last_sent_period' => $period]);
                $sent++;
            }
        }

        $this->info("Sent monthly financial statements to {$sent} compan(y/ies) for {$period}.");

        return self::SUCCESS;
    }

    private function sendFor(Company $company, Carbon $periodStart, Carbon $periodEnd): bool
    {
        $recipients = User::where('company_id', $company->id)
            ->get()
            ->filter(fn (User $user) => $user->hasPermission('reports'))
            ->pluck('email')
            ->filter()
            ->unique();

        if ($recipients->isEmpty()) {
            return false;
        }

        $reportService = app(FinancialReportService::class);
        $renderer = app(MpdfRenderer::class);

        $balanceSheetData = $reportService->balanceSheet($company, $periodEnd);
        $balanceSheetPdf = $renderer->render('user.reports.pdf.balance-sheet', [
            'company' => $company, 'asOf' => $periodEnd, 'priorAsOf' => null, 'compare' => 'none',
            'template' => $company->defaultTemplateFor('balance_sheet'),
        ] + $balanceSheetData);

        $incomeStatement = $reportService->incomeStatement($company, $periodStart, $periodEnd);
        $incomeStatementPdf = $renderer->render('user.reports.pdf.income-statement', [
            'company' => $company, 'period' => ['from' => $periodStart, 'to' => $periodEnd],
            'revenueLines' => $incomeStatement['revenueLines'], 'expenseLines' => $incomeStatement['expenseLines'],
            'netSales' => $incomeStatement['netSales'], 'grossProfit' => $incomeStatement['grossProfit'],
            'operatingProfit' => $incomeStatement['operatingProfit'], 'netProfit' => $incomeStatement['netProfit'],
            'priorPeriodAdjustments' => $incomeStatement['priorPeriodAdjustments'],
            'compare' => 'none', 'comparisonPeriod' => null, 'priorStatement' => null,
            'template' => $company->defaultTemplateFor('income_statement'),
        ]);

        Mail::to($recipients->all())->send(new MonthlyFinancialStatementsMail($company, $periodStart, $periodEnd, $balanceSheetPdf, $incomeStatementPdf));

        return true;
    }
}
