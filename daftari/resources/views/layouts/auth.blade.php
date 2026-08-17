<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · Daftari</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="relative flex min-h-screen flex-col items-center justify-center overflow-hidden bg-slate-50 px-4 py-12">
    <div class="bg-grid pointer-events-none absolute inset-0 [mask-image:radial-gradient(ellipse_70%_60%_at_50%_0%,black,transparent)]"></div>
    <div class="pointer-events-none absolute -top-24 start-1/2 h-96 w-96 -translate-x-1/2 rounded-full bg-brand-200/40 blur-3xl"></div>

    <a href="{{ route('home') }}" class="relative mb-8 flex animate-fade-up items-center gap-2 text-xl font-bold text-brand-800">
        <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-brand-500 to-brand-700 text-white shadow-glow">د</span>
        Daftari
    </a>

    <div class="relative w-full max-w-md animate-fade-up rounded-2xl border border-slate-100 bg-white p-8 shadow-card-hover [animation-delay:80ms]">
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
