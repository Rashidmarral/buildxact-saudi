<?php

namespace App\Services\Payroll;

use App\Models\Company;
use App\Models\PayrollRun;

/**
 * WPS (Wage Protection System) SIF — the Salary Information File Saudi
 * employers upload to their bank each pay cycle so MHRSD can confirm
 * wages were actually paid. Written from the publicly documented SIF
 * structure common to WPS-participating Saudi banks (a header record, one
 * detail record per employee, a trailer record) — the exact field order/
 * delimiter can differ by bank, and this has not been validated against
 * a real bank's current WPS portal. Confirm your bank's specific SIF
 * template before submitting a real file; some banks require a specific
 * fixed-width or SARIE-coded variant instead of this comma-delimited one.
 */
class WpsSifExporter
{
    public function export(Company $company, PayrollRun $payrollRun): string
    {
        $payrollRun->loadMissing('items.employee');

        $lines = [];
        $lines[] = $this->headerRecord($company, $payrollRun);

        foreach ($payrollRun->items as $item) {
            $lines[] = $this->detailRecord($item);
        }

        $lines[] = $this->trailerRecord($payrollRun);

        return implode("\r\n", $lines)."\r\n";
    }

    private function headerRecord(Company $company, PayrollRun $payrollRun): string
    {
        $fields = [
            'H',
            $company->cr_number ?: $company->slug,
            $this->sanitize($company->name),
            $payrollRun->pay_date->format('Ymd'),
            sprintf('%04d%02d', $payrollRun->period_year, $payrollRun->period_month),
            (string) $payrollRun->items->count(),
            number_format((float) $payrollRun->total_net, 2, '.', ''),
            $company->currency ?: 'SAR',
        ];

        return implode(',', $fields);
    }

    private function detailRecord($item): string
    {
        $employee = $item->employee;

        $fields = [
            'D',
            $employee->national_id ?? '',
            $this->sanitize($employee->full_name),
            $employee->bank_name ?? '',
            $employee->iban ?? '',
            number_format((float) $item->basic_salary, 2, '.', ''),
            number_format((float) $item->housing_allowance, 2, '.', ''),
            number_format((float) ($item->transport_allowance + $item->other_allowance), 2, '.', ''),
            number_format((float) ($item->gosi_employee_contribution + $item->other_deductions), 2, '.', ''),
            number_format((float) $item->net_salary, 2, '.', ''),
            (string) $item->days_worked,
        ];

        return implode(',', $fields);
    }

    private function trailerRecord(PayrollRun $payrollRun): string
    {
        $fields = [
            'T',
            (string) $payrollRun->items->count(),
            number_format((float) $payrollRun->total_net, 2, '.', ''),
        ];

        return implode(',', $fields);
    }

    /**
     * Strips commas/CR/LF from a free-text field so it can't corrupt the
     * comma-delimited record it sits in.
     */
    private function sanitize(?string $value): string
    {
        return str_replace([',', "\r", "\n"], ' ', (string) $value);
    }
}
