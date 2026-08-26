<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ \App\Support\Locales::dir(app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · Daftari</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50">
    <header class="border-b border-slate-100 bg-white px-4 py-4">
        <div class="mx-auto flex max-w-3xl items-center justify-between">
            <a href="{{ Route::has('portal.dashboard') ? route('portal.dashboard') : route('home') }}" class="flex items-center gap-2 text-lg font-bold text-brand-800">
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br from-brand-500 to-brand-700 text-sm text-white shadow-glow">د</span>
                Daftari
            </a>
            @if (request()->attributes->get('portalClient'))
                <nav class="flex items-center gap-4 text-sm font-medium text-slate-500">
                    <a href="{{ route('portal.dashboard') }}" class="hover:text-brand-700 {{ request()->routeIs('portal.dashboard') ? 'text-brand-700' : '' }}">{{ __('Dashboard') }}</a>
                    <a href="{{ route('portal.invoices') }}" class="hover:text-brand-700 {{ request()->routeIs('portal.invoices') ? 'text-brand-700' : '' }}">{{ __('Invoices') }}</a>
                    <a href="{{ route('portal.statement') }}" class="hover:text-brand-700 {{ request()->routeIs('portal.statement') ? 'text-brand-700' : '' }}">{{ __('Statement') }}</a>
                    <form method="POST" action="{{ route('portal.logout') }}">
                        @csrf
                        <button type="submit" class="hover:text-brand-700">{{ __('Sign out') }}</button>
                    </form>
                </nav>
            @endif
        </div>
    </header>

    <main class="mx-auto max-w-3xl px-4 py-8">
        @if (session('status'))
            <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif
        @yield('content')
    </main>

    <footer class="mx-auto max-w-3xl px-4 pb-8 text-center text-xs text-slate-400">
        {{ __('Powered by') }} Daftari
    </footer>
</body>
</html>
