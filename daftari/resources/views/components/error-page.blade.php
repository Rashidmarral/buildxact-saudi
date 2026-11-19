@php
    // Deliberately self-contained: no @vite(), no external font request, no
    // dependency on Setting::get()/platform branding or any other call that
    // could throw a second time. This renders at the moment something has
    // already gone wrong — a 500 can mean the database is unreachable, a
    // 503 can mean the whole app/build is down — so the one page meant to
    // always be there for the user must not itself depend on anything that
    // can fail (a missing Vite manifest, a DNS lookup to Google Fonts, a
    // slow network). Every style below is inlined; the font stack is the
    // OS's own, which already renders Arabic cleanly on every platform this
    // ships to.
    $icons = [
        '401' => 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V9a4 4 0 10-8 0v2',
        '403' => 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V9a4 4 0 10-8 0v2',
        '404' => 'M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
        '419' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
        '429' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
        '500' => 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007',
        '503' => 'M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01M4.93 12.5a10 10 0 0114.14 0M1.5 9a14.5 14.5 0 0121 0',
    ];
    $icon = $icons[$code] ?? $icons['500'];
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ \App\Support\Locales::dir(app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $title }} · Daftari</title>
    <style>
        :root {
            color-scheme: light;
            --brand-50: #f3f6fb; --brand-100: #e4eaf6; --brand-400: #7b9bcf;
            --brand-500: #52709f; --brand-600: #334e82; --brand-700: #28406c; --brand-900: #16233c;
            --accent-500: #b8802c;
            --ink: #1f2937; --muted: #64748b; --line: #e4e9f1;
        }
        * { box-sizing: border-box; }
        html, body {
            margin: 0; min-height: 100%;
            font-family: -apple-system, 'Segoe UI', 'Tahoma', 'Noto Sans Arabic', Roboto, Helvetica, Arial, sans-serif;
            background: var(--brand-900);
            color: var(--ink);
        }
        body {
            min-height: 100vh;
            display: flex; align-items: center; justify-content: center;
            padding: 24px;
            background:
                radial-gradient(60rem 36rem at 15% -10%, rgba(123, 155, 207, .35), transparent 60%),
                radial-gradient(50rem 30rem at 110% 10%, rgba(184, 128, 44, .22), transparent 55%),
                linear-gradient(160deg, var(--brand-900), var(--brand-700) 55%, var(--brand-600));
        }
        .card {
            width: 100%; max-width: 30rem;
            background: #ffffff;
            border-radius: 20px;
            padding: 44px 36px 36px;
            text-align: center;
            box-shadow: 0 30px 60px -20px rgba(10, 20, 40, .45), 0 0 0 1px rgba(255,255,255,.06);
            animation: rise .5s cubic-bezier(.16,1,.3,1) both;
        }
        @keyframes rise {
            from { opacity: 0; transform: translateY(14px) scale(.985); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        .badge {
            width: 64px; height: 64px; margin: 0 auto 20px;
            border-radius: 18px;
            display: flex; align-items: center; justify-content: center;
            background: linear-gradient(145deg, var(--brand-400), var(--brand-600));
            box-shadow: 0 10px 22px -8px rgba(51, 78, 130, .55);
        }
        .badge svg { width: 30px; height: 30px; stroke: #fff; }
        .eyebrow {
            margin: 0; font-size: 12px; font-weight: 700; letter-spacing: .14em;
            text-transform: uppercase; color: var(--brand-600);
        }
        h1 { margin: 6px 0 0; font-size: 21px; font-weight: 800; color: #0f1b2d; }
        p.msg { margin: 12px 0 0; font-size: 14px; line-height: 1.6; color: var(--muted); }
        .actions { margin-top: 26px; display: flex; align-items: center; justify-content: center; gap: 10px; flex-wrap: wrap; }
        .btn {
            display: inline-flex; align-items: center; justify-content: center;
            padding: 10px 18px; border-radius: 10px; font-size: 14px; font-weight: 700;
            text-decoration: none; cursor: pointer; border: 1px solid transparent;
            transition: transform .12s ease, box-shadow .12s ease, background-color .12s ease;
        }
        .btn:active { transform: translateY(1px); }
        .btn-primary { background: var(--brand-600); color: #fff; box-shadow: 0 8px 18px -6px rgba(51,78,130,.5); }
        .btn-primary:hover { background: var(--brand-700); }
        .btn-ghost { background: #fff; color: var(--muted); border-color: var(--line); }
        .btn-ghost:hover { border-color: #c3d0e9; color: var(--ink); }
        .footer { margin-top: 28px; font-size: 11px; color: #9aa6b8; letter-spacing: .04em; }
    </style>
</head>
<body>
    <div class="card" role="alert">
        <div class="badge" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $icon }}"/></svg>
        </div>
        <p class="eyebrow">{{ __('Error :code', ['code' => $code]) }}</p>
        <h1>{{ $title }}</h1>
        <p class="msg">{{ $message }}</p>
        <div class="actions">
            {{ $slot }}
        </div>
        <p class="footer">Daftari</p>
    </div>
</body>
</html>
