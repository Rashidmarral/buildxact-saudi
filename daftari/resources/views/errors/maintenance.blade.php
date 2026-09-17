<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ \App\Support\Locales::dir(app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Under maintenance') }} · Daftari</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])
</head>
<body class="flex min-h-screen items-center justify-center bg-slate-50 px-4 text-slate-800 antialiased">
    <div class="max-w-md text-center">
        <span class="mx-auto mb-6 inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-brand-400 to-brand-600 text-2xl font-bold text-white shadow-glow">د</span>
        <h1 class="text-xl font-bold text-slate-900">{{ __('Daftari is briefly offline for maintenance') }}</h1>
        <p class="mt-3 text-sm text-slate-500">
            {{ $message ?: __("We'll be back shortly. Thanks for your patience.") }}
        </p>
    </div>
</body>
</html>
