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
</body>
</html>
