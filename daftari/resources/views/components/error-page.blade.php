@php
    // Deliberately self-contained and DB-free: this renders at the moment
    // something has already gone wrong (a 500 can mean the database itself
    // is unreachable), so it must not depend on Setting::get()/platform
    // branding or anything else that could throw a second time and fall
    // back to the framework's bare, unstyled default instead. Same
    // convention as errors/maintenance.blade.php and
    // errors/registration-closed.blade.php.
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ \App\Support\Locales::dir(app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · Daftari</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])
</head>
<body class="flex min-h-screen items-center justify-center bg-slate-50 px-4 text-slate-800 antialiased">
    <div class="max-w-md text-center">
        <span class="mx-auto mb-6 inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-brand-400 to-brand-600 text-2xl font-bold text-white shadow-glow">د</span>
        <p class="text-sm font-semibold uppercase tracking-wide text-brand-600">{{ $code }}</p>
        <h1 class="mt-1 text-xl font-bold text-slate-900">{{ $title }}</h1>
        <p class="mt-3 text-sm text-slate-500">{{ $message }}</p>
        <div class="mt-6 flex items-center justify-center gap-3">
            {{ $slot }}
        </div>
    </div>
</body>
</html>
