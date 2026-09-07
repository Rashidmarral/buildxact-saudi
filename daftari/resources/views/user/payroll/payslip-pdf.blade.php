<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1e293b; }
    h1 { font-size: 16px; margin: 0 0 4px; }
    .muted { color: #64748b; }
    table { width: 100%; border-collapse: collapse; margin-top: 12px; }
    th, td { padding: 6px 8px; text-align: left; }
    th { background: #f1f5f9; font-size: 10px; text-transform: uppercase; color: #64748b; }
    tr { border-bottom: 1px solid #e2e8f0; }
    .amount { text-align: right; }
    .total-row td { font-weight: bold; border-top: 2px solid #0f766e; }
</style>
</head>
<body>
    <h1>{{ $company->name }}</h1>
    <p class="muted">{{ __('Payslip') }} — {{ \Carbon\Carbon::create($payrollRun->period_year, $payrollRun->period_month, 1)->translatedFormat('F Y') }}</p>

    <table>
        <tr>
            <td><strong>{{ __('Employee') }}:</strong> {{ $item->employee->full_name }}</td>
            <td><strong>{{ __('Employee no.') }}:</strong> {{ $item->employee->employee_number }}</td>
        </tr>
        <tr>
            <td><strong>{{ __('Job title') }}:</strong> {{ $item->employee->job_title ?: '—' }}</td>
            <td><strong>{{ __('Pay date') }}:</strong> {{ $payrollRun->pay_date->format('Y-m-d') }}</td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th>{{ __('Earnings') }}</th>
                <th class="amount">{{ __('Amount') }}</th>
            </tr>
        </thead>
        <tbody>
            <tr><td>{{ __('Basic salary') }}</td><td class="amount">{{ number_format($item->basic_salary, 2) }}</td></tr>
            <tr><td>{{ __('Housing allowance') }}</td><td class="amount">{{ number_format($item->housing_allowance, 2) }}</td></tr>
            <tr><td>{{ __('Transport allowance') }}</td><td class="amount">{{ number_format($item->transport_allowance, 2) }}</td></tr>
            <tr><td>{{ __('Other allowance') }}</td><td class="amount">{{ number_format($item->other_allowance, 2) }}</td></tr>
            <tr class="total-row"><td>{{ __('Gross salary') }}</td><td class="amount">{{ number_format($item->gross_salary, 2) }}</td></tr>
        </tbody>
    </table>

    <table>
        <thead>
            <tr>
                <th>{{ __('Deductions') }}</th>
                <th class="amount">{{ __('Amount') }}</th>
            </tr>
        </thead>
        <tbody>
            <tr><td>{{ __('GOSI (employee share)') }}</td><td class="amount">{{ number_format($item->gosi_employee_contribution, 2) }}</td></tr>
            <tr><td>{{ __('Other deductions') }}</td><td class="amount">{{ number_format($item->other_deductions, 2) }}</td></tr>
            <tr class="total-row"><td>{{ __('Net salary') }}</td><td class="amount">{{ number_format($item->net_salary, 2) }}</td></tr>
        </tbody>
    </table>

    <p class="muted" style="margin-top: 20px;">{{ __('This payslip is computer-generated and does not require a signature.') }}</p>
</body>
</html>
