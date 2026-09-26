<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ \App\Support\Locales::dir(app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title') · Daftari Setup</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    {{-- Deliberately not layouts.auth/app: those read platform branding from
    the `settings` table via App\Support\PlatformBranding, which doesn't
    exist yet on a fresh install (Steps 1-4 run before Step 5 migrates the
    database at all). This layout is fully static — no DB, no Company,
    no Setting — by construction. --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="relative flex min-h-screen flex-col items-center overflow-x-hidden overflow-y-auto bg-slate-50 px-4 py-10">
    <div class="bg-grid pointer-events-none absolute inset-0 [mask-image:radial-gradient(ellipse_70%_60%_at_50%_0%,black,transparent)]"></div>
    <div class="pointer-events-none absolute -top-24 start-1/2 h-96 w-96 -translate-x-1/2 rounded-full bg-brand-200/40 blur-3xl"></div>

    <div class="relative mb-6 flex items-center gap-2 text-xl font-bold text-brand-800">
        <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-brand-500 to-brand-700 text-white shadow-glow">د</span>
        Daftari
        <span class="ms-1 rounded-full bg-slate-200 px-2 py-0.5 text-xs font-semibold text-slate-500">{{ __('Setup') }}</span>
    </div>

    <ol class="relative mb-8 flex w-full max-w-3xl items-center justify-between gap-1 px-2 text-xs font-semibold text-slate-400">
        @foreach ([
            1 => __('Requirements'),
            2 => __('Database'),
            3 => __('Application'),
            4 => __('Admin'),
            5 => __('Install'),
            6 => __('Complete'),
        ] as $n => $label)
            <li class="flex flex-1 flex-col items-center gap-1.5">
                <span @class([
                    'flex h-7 w-7 items-center justify-center rounded-full border-2 text-[11px]',
                    'border-brand-600 bg-brand-600 text-white' => $n < ($step ?? 1),
                    'border-brand-600 bg-white text-brand-700' => $n === ($step ?? 1),
                    'border-slate-200 bg-white text-slate-400' => $n > ($step ?? 1),
                ])>
                    @if ($n < ($step ?? 1))
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" class="h-3.5 w-3.5"><path d="M5 13l4 4L19 7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    @else
                        {{ $n }}
                    @endif
                </span>
                <span class="hidden text-center sm:block @if($n === ($step ?? 1)) text-brand-700 @endif">{{ $label }}</span>
            </li>
        @endforeach
    </ol>

    <div class="relative w-full max-w-2xl animate-fade-up rounded-2xl border border-slate-100 bg-white p-8 shadow-card-hover">
        @if ($errors->any())
            <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                <ul class="list-disc ps-4 space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </div>

    <a href="{{ route('locale.switch', app()->getLocale() === 'ar' ? 'en' : 'ar') }}" class="relative mt-6 text-sm text-slate-500 transition-colors hover:text-brand-700">
        {{ app()->getLocale() === 'ar' ? 'View in English' : 'عرض بالعربية' }}
    </a>
</body>
</html>
