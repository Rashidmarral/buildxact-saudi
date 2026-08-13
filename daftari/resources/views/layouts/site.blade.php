<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', __('Daftari — Saudi VAT Invoicing & Accounting'))</title>
    <meta name="description" content="{{ __('Daftari is a subscription accounting and VAT e-invoicing platform built for Saudi businesses — invoices, expenses, VAT reports, and ZATCA-ready QR codes.') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-white text-slate-800 antialiased">
    <header class="border-b border-slate-100">
        <div class="mx-auto max-w-7xl px-6 py-4 flex items-center justify-between">
            <a href="{{ route('home') }}" class="flex items-center gap-2 font-bold text-xl text-brand-800">
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-brand-600 text-white">د</span>
                Daftari
            </a>
            <nav class="hidden md:flex items-center gap-8 text-sm font-medium text-slate-600">
                <a href="{{ route('features') }}" class="hover:text-brand-700">{{ __('Features') }}</a>
                <a href="{{ route('pricing') }}" class="hover:text-brand-700">{{ __('Pricing') }}</a>
                <a href="{{ route('compliance') }}" class="hover:text-brand-700">{{ __('Compliance') }}</a>
                <a href="{{ route('about') }}" class="hover:text-brand-700">{{ __('About') }}</a>
                <a href="{{ route('contact') }}" class="hover:text-brand-700">{{ __('Contact') }}</a>
            </nav>
            <div class="flex items-center gap-3">
                <a href="{{ route('locale.switch', app()->getLocale() === 'ar' ? 'en' : 'ar') }}" class="text-sm text-slate-500 hover:text-brand-700">
                    {{ app()->getLocale() === 'ar' ? 'EN' : 'العربية' }}
                </a>
                @auth
                    <a href="{{ auth()->user()->isSuperAdmin() ? route('admin.dashboard') : route('app.dashboard') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Go to Dashboard') }}</a>
                @else
                    <a href="{{ route('login') }}" class="text-sm font-medium text-slate-600 hover:text-brand-700">{{ __('Log in') }}</a>
                    <a href="{{ route('register') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Start free trial') }}</a>
                @endauth
            </div>
        </div>
    </header>

    <main>
        @if (session('status'))
            <div class="mx-auto max-w-7xl px-6 pt-4">
                <div class="rounded-lg bg-brand-50 border border-brand-200 text-brand-800 px-4 py-3 text-sm">{{ session('status') }}</div>
            </div>
        @endif
        @yield('content')
    </main>

    <footer class="mt-24 border-t border-slate-100 bg-slate-50">
        <div class="mx-auto max-w-7xl px-6 py-12 grid grid-cols-2 md:grid-cols-4 gap-8 text-sm">
            <div class="col-span-2 md:col-span-1">
                <div class="flex items-center gap-2 font-bold text-lg text-brand-800">
                    <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-brand-600 text-white text-sm">د</span>
                    Daftari
                </div>
                <p class="mt-3 text-slate-500">{{ __('Subscription accounting & VAT e-invoicing for Saudi businesses.') }}</p>
            </div>
            <div>
                <h4 class="font-semibold text-slate-700">{{ __('Product') }}</h4>
                <ul class="mt-3 space-y-2 text-slate-500">
                    <li><a href="{{ route('features') }}" class="hover:text-brand-700">{{ __('Features') }}</a></li>
                    <li><a href="{{ route('pricing') }}" class="hover:text-brand-700">{{ __('Pricing') }}</a></li>
                    <li><a href="{{ route('compliance') }}" class="hover:text-brand-700">{{ __('Compliance') }}</a></li>
                </ul>
            </div>
            <div>
                <h4 class="font-semibold text-slate-700">{{ __('Company') }}</h4>
                <ul class="mt-3 space-y-2 text-slate-500">
                    <li><a href="{{ route('about') }}" class="hover:text-brand-700">{{ __('About') }}</a></li>
                    <li><a href="{{ route('contact') }}" class="hover:text-brand-700">{{ __('Contact') }}</a></li>
                </ul>
            </div>
            <div>
                <h4 class="font-semibold text-slate-700">{{ __('Legal') }}</h4>
                <ul class="mt-3 space-y-2 text-slate-500">
                    <li><a href="{{ route('legal', 'terms') }}" class="hover:text-brand-700">{{ __('Terms of Service') }}</a></li>
                    <li><a href="{{ route('legal', 'privacy') }}" class="hover:text-brand-700">{{ __('Privacy Policy') }}</a></li>
                </ul>
            </div>
        </div>
        <div class="border-t border-slate-200 py-6 text-center text-xs text-slate-400">
            &copy; {{ now()->year }} Daftari. {{ __('All rights reserved.') }}
        </div>
    </footer>
</body>
</html>
