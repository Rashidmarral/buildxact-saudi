<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ \App\Support\Locales::dir(app()->getLocale()) }}">
<head>
<meta charset="utf-8">
<style>
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 12px; color: #1e293b; }
    h1 { font-size: 18px; margin-bottom: 2px; }
    p.sub { color: #64748b; margin-top: 0; }
    .banner { margin-top: 12px; padding: 8px 10px; border-radius: 4px; font-weight: bold; }
    .banner.ok { background: #ecfdf5; color: #047857; }
    .banner.warning { background: #fffbeb; color: #b45309; }
    .banner.critical { background: #fef2f2; color: #b91c1c; }
    .section { margin-top: 16px; }
    .section-title { font-weight: bold; font-size: 13px; }
    .badge { display: inline-block; padding: 1px 6px; border-radius: 3px; font-size: 10px; font-weight: bold; }
    .badge.ok { background: #ecfdf5; color: #047857; }
    .badge.warning { background: #fffbeb; color: #b45309; }
    .badge.critical { background: #fef2f2; color: #b91c1c; }
    .summary { color: #475569; margin: 3px 0; }
    table { width: 100%; border-collapse: collapse; margin-top: 6px; }
    th, td { padding: 4px 6px; text-align: {{ app()->getLocale() === 'ar' ? 'right' : 'left' }}; font-size: 11px; }
    th { border-bottom: 1px solid #cbd5e1; color: #64748b; font-weight: normal; }
    td { border-bottom: 1px solid #f1f5f9; }
    .text-end { text-align: right; }
    .entry-row td { padding-top: 10px; border-bottom: none; font-weight: bold; color: #0f172a; background: #f8fafc; }
    .entry-meta { font-weight: normal; color: #64748b; font-size: 10px; }
    .line-row td { padding-left: 14px; color: #334155; }
    .totals-row td { border-top: 1.5pt solid #1e293b; border-bottom: none; font-weight: bold; padding-top: 6px; }
    .page-break { page-break-before: always; }
</style>
</head>
<body>
    <h1>{{ $company->name }}</h1>
    <p class="sub">{{ __('Company Audit') }} — {{ $period['from']->format('Y-m-d') }} &rarr; {{ $period['to']->format('Y-m-d') }}</p>

    @php
        $bannerText = match ($overallStatus) {
            'ok' => __('Audit-ready'),
            'warning' => __('Needs attention'),
            default => __('Issues found'),
        };
    @endphp
    <div class="banner {{ $overallStatus }}">{{ $bannerText }}</div>

    @foreach ($sections as $section)
        <div class="section">
            <div class="section-title">{{ $section['label'] }} <span class="badge {{ $section['status'] }}">{{ ucfirst($section['status']) }}</span></div>
            <p class="summary">{{ $section['summary'] }}</p>

            @if ($section['items']->isNotEmpty())
                <table>
                    <thead>
                        <tr><th>{{ __('Finding') }}</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($section['items'] as $item)
                            <tr><td>{{ $item['label'] }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @endforeach

    <div class="section page-break">
        <div class="section-title">{{ __('Transaction detail') }} <span class="entry-meta">({{ $transactions->count() }} {{ __('entries') }})</span></div>
        <p class="summary">{{ __('Every posted transaction in this period, with the accounts each one debited and credited.') }}</p>

        @if ($transactions->isEmpty())
            <p class="summary">{{ __('No transactions were posted in this period.') }}</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('Entry / Account') }}</th>
                        <th>{{ __('Source') }}</th>
                        <th class="text-end">{{ __('Debit') }}</th>
                        <th class="text-end">{{ __('Credit') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($transactions as $entry)
                        <tr class="entry-row">
                            <td>{{ $entry->entry_date->format('Y-m-d') }}</td>
                            <td colspan="2">
                                {{ $entry->entry_number }}
                                <span class="entry-meta">— {{ $entry->description }}</span>
                            </td>
                            <td class="text-end">{{ \App\Support\Money::format($entry->totalDebit()) }}</td>
                            <td class="text-end"></td>
                        </tr>
                        @foreach ($entry->lines as $line)
                            <tr class="line-row">
                                <td></td>
                                <td>{{ $line->account?->name ?? __('—') }}</td>
                                <td>{{ $entry->source_label }}</td>
                                <td class="text-end">{{ $line->debit > 0 ? \App\Support\Money::format($line->debit) : '' }}</td>
                                <td class="text-end">{{ $line->credit > 0 ? \App\Support\Money::format($line->credit) : '' }}</td>
                            </tr>
                        @endforeach
                    @endforeach
                    <tr class="totals-row">
                        <td colspan="3">{{ __('Total') }}</td>
                        <td class="text-end">{{ \App\Support\Money::format($transactionTotals['debit']) }}</td>
                        <td class="text-end">{{ \App\Support\Money::format($transactionTotals['credit']) }}</td>
                    </tr>
                </tbody>
            </table>
        @endif
    </div>
</body>
</html>
