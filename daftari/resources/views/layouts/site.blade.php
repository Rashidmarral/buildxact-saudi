<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ \App\Support\Locales::dir(app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', __('Daftari — Saudi VAT Invoicing & Accounting'))</title>
    <meta name="description" content="{{ __('Daftari is a subscription accounting and VAT e-invoicing platform built for Saudi businesses — invoices, expenses, VAT reports, and ZATCA-ready QR codes.') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @php($__branding = \App\Support\PlatformBranding::all())
    @include('partials.branding-overrides')
</head>
@php($__siteHeader = \App\Models\CmsSection::globalBlock('site_header'))
<body class="bg-white text-slate-800 antialiased" x-data="{ mobileOpen: false }">
    @if ($__siteHeader && ($__siteHeader->title() || $__siteHeader->badge()))
        <div class="relative overflow-hidden bg-gradient-to-r from-brand-600 to-brand-700 px-6 py-2.5 text-center text-sm text-white">
            <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-center gap-x-2 gap-y-1">
                @if ($__siteHeader->badge())
                    <span class="rounded-full bg-white/15 px-2 py-0.5 text-xs font-semibold">{{ $__siteHeader->badge() }}</span>
                @endif
                @if ($__siteHeader->title())
                    <span class="font-medium">{{ $__siteHeader->title() }}</span>
                @endif
                @if ($__siteHeader->linkText())
                    <a href="{{ $__siteHeader->link_url ?: route('pricing') }}" class="font-semibold underline decoration-white/50 underline-offset-2 hover:decoration-white">{{ $__siteHeader->linkText() }}</a>
                @endif
            </div>
        </div>
    @endif

    <header class="sticky top-0 z-40 border-b border-slate-100 bg-white/80 backdrop-blur-md">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-4">
            <a href="{{ route('home') }}" class="flex items-center gap-2 text-xl font-bold text-brand-800">
                @if ($__branding['logo_path'])
                    <img src="{{ Storage::url($__branding['logo_path']) }}" alt="{{ $__branding['name'] }}" class="h-9 w-9 rounded-xl object-cover shadow-glow">
                @else
                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-brand-500 to-brand-700 text-white shadow-glow">د</span>
                @endif
                {{ $__branding['name'] }}
            </a>
            <nav class="hidden items-center gap-8 text-sm font-medium text-slate-600 md:flex">
                <a href="{{ route('features') }}" class="transition-colors hover:text-brand-700">{{ __('Features') }}</a>
                <a href="{{ route('pricing') }}" class="transition-colors hover:text-brand-700">{{ __('Pricing') }}</a>
                <a href="{{ route('compliance') }}" class="transition-colors hover:text-brand-700">{{ __('Compliance') }}</a>
                <a href="{{ route('about') }}" class="transition-colors hover:text-brand-700">{{ __('About') }}</a>
                <a href="{{ route('contact') }}" class="transition-colors hover:text-brand-700">{{ __('Contact') }}</a>
                <div class="group relative">
                    <button type="button" class="flex items-center gap-1 transition-colors hover:text-brand-700">
                        {{ __('Free Tools & Templates') }}
                        <svg class="h-3 w-3 transition-transform group-hover:rotate-180" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>
                    </button>
                    <div class="invisible absolute start-0 top-full z-20 w-56 pt-2 opacity-0 transition-all duration-200 group-hover:visible group-hover:opacity-100">
                        <div class="animate-scale-in rounded-xl border border-slate-100 bg-white py-2 shadow-card-hover">
                            <a href="{{ route('tools.index') }}" class="block px-4 py-2 text-sm hover:bg-slate-50">{{ __('All accounting tools') }}</a>
                            <a href="{{ route('tools.vat') }}" class="block px-4 py-2 text-sm hover:bg-slate-50">{{ __('VAT calculator') }}</a>
                            <a href="{{ route('tools.zakat') }}" class="block px-4 py-2 text-sm hover:bg-slate-50">{{ __('Zakat calculator') }}</a>
                            <a href="{{ route('tools.gosi') }}" class="block px-4 py-2 text-sm hover:bg-slate-50">{{ __('GOSI calculator') }}</a>
                            <a href="{{ route('tools.invoice-generator') }}" class="block px-4 py-2 text-sm hover:bg-slate-50">{{ __('Free invoice generator') }}</a>
                            <div class="my-1 border-t border-slate-100"></div>
                            <a href="{{ route('compliance') }}" class="block px-4 py-2 text-sm hover:bg-slate-50">{{ __('Compliance') }}</a>
                            <a href="{{ route('certificates') }}" class="block px-4 py-2 text-sm hover:bg-slate-50">{{ __('Certificates') }}</a>
                            <a href="{{ route('glossary') }}" class="block px-4 py-2 text-sm hover:bg-slate-50">{{ __('Glossary') }}</a>
                        </div>
                    </div>
                </div>
            </nav>
            <div class="hidden items-center gap-3 md:flex">
                <a href="{{ route('locale.switch', app()->getLocale() === 'ar' ? 'en' : 'ar') }}" class="text-sm text-slate-500 transition-colors hover:text-brand-700">
                    {{ app()->getLocale() === 'ar' ? 'EN' : 'العربية' }}
                </a>
                @auth
                    <a href="{{ auth()->user()->isSuperAdmin() ? route('admin.dashboard') : route('app.dashboard') }}" class="btn-shine rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-soft transition-all hover:-translate-y-0.5 hover:bg-brand-700 hover:shadow-card">{{ __('Go to Dashboard') }}</a>
                @else
                    <a href="{{ route('login') }}" class="text-sm font-medium text-slate-600 transition-colors hover:text-brand-700">{{ __('Log in') }}</a>
                    <a href="{{ route('register') }}" class="btn-shine rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-soft transition-all hover:-translate-y-0.5 hover:bg-brand-700 hover:shadow-card">{{ __('Start free trial') }}</a>
                @endauth
            </div>
            <button type="button" @click="mobileOpen = !mobileOpen" class="rounded-lg p-2 text-slate-600 hover:bg-slate-100 md:hidden">
                <svg x-show="!mobileOpen" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                <svg x-show="mobileOpen" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" style="display:none"><path d="M6 6l12 12M18 6L6 18"/></svg>
            </button>
        </div>

        <div x-show="mobileOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="border-t border-slate-100 bg-white px-6 py-4 md:hidden" style="display:none">
            <nav class="flex flex-col gap-1 text-sm font-medium text-slate-600">
                <a href="{{ route('features') }}" class="rounded-lg px-3 py-2.5 hover:bg-slate-50">{{ __('Features') }}</a>
                <a href="{{ route('pricing') }}" class="rounded-lg px-3 py-2.5 hover:bg-slate-50">{{ __('Pricing') }}</a>
                <a href="{{ route('compliance') }}" class="rounded-lg px-3 py-2.5 hover:bg-slate-50">{{ __('Compliance') }}</a>
                <a href="{{ route('about') }}" class="rounded-lg px-3 py-2.5 hover:bg-slate-50">{{ __('About') }}</a>
                <a href="{{ route('contact') }}" class="rounded-lg px-3 py-2.5 hover:bg-slate-50">{{ __('Contact') }}</a>
                <a href="{{ route('tools.index') }}" class="rounded-lg px-3 py-2.5 hover:bg-slate-50">{{ __('Free Tools & Templates') }}</a>
                <a href="{{ route('certificates') }}" class="rounded-lg px-3 py-2.5 hover:bg-slate-50">{{ __('Certificates') }}</a>
                <a href="{{ route('glossary') }}" class="rounded-lg px-3 py-2.5 hover:bg-slate-50">{{ __('Glossary') }}</a>
                <div class="my-2 border-t border-slate-100"></div>
                <a href="{{ route('locale.switch', app()->getLocale() === 'ar' ? 'en' : 'ar') }}" class="rounded-lg px-3 py-2.5 hover:bg-slate-50">{{ app()->getLocale() === 'ar' ? 'English' : 'العربية' }}</a>
                @auth
                    <a href="{{ auth()->user()->isSuperAdmin() ? route('admin.dashboard') : route('app.dashboard') }}" class="mt-2 rounded-lg bg-brand-600 px-3 py-2.5 text-center font-semibold text-white">{{ __('Go to Dashboard') }}</a>
                @else
                    <a href="{{ route('login') }}" class="rounded-lg px-3 py-2.5 hover:bg-slate-50">{{ __('Log in') }}</a>
                    <a href="{{ route('register') }}" class="mt-2 rounded-lg bg-brand-600 px-3 py-2.5 text-center font-semibold text-white">{{ __('Start free trial') }}</a>
                @endauth
            </nav>
        </div>
    </header>

    <main>
        @if (session('status'))
            <div class="mx-auto max-w-7xl px-6 pt-4">
                <div class="rounded-lg border border-brand-200 bg-brand-50 px-4 py-3 text-sm text-brand-800">{{ session('status') }}</div>
            </div>
        @endif
        @yield('content')
    </main>

    <footer class="mt-24 border-t border-slate-100 bg-slate-50">
        <div class="mx-auto grid max-w-7xl grid-cols-2 gap-8 px-6 py-12 text-sm md:grid-cols-5">
            <div class="col-span-2 md:col-span-1">
                <div class="flex items-center gap-2 text-lg font-bold text-brand-800">
                    @if ($__branding['logo_path'])
                        <img src="{{ Storage::url($__branding['logo_path']) }}" alt="{{ $__branding['name'] }}" class="h-7 w-7 rounded-lg object-cover">
                    @else
                        <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-gradient-to-br from-brand-500 to-brand-700 text-sm text-white">د</span>
                    @endif
                    {{ $__branding['name'] }}
                </div>
                @php($__siteFooter = \App\Models\CmsSection::globalBlock('site_footer'))
                <p class="mt-3 text-slate-500">{{ $__siteFooter?->subtitle() ?: __('Subscription accounting & VAT e-invoicing for Saudi businesses.') }}</p>
                @if ($__branding['vat_number'] || $__branding['cr_number'] || $__branding['address'])
                    <ul class="mt-3 space-y-1 text-xs text-slate-400">
                        @if ($__branding['vat_number'])
                            <li>{{ __('VAT No.') }} {{ $__branding['vat_number'] }}</li>
                        @endif
                        @if ($__branding['cr_number'])
                            <li>{{ __('CR No.') }} {{ $__branding['cr_number'] }}</li>
                        @endif
                        @if ($__branding['address'])
                            <li>{{ $__branding['address'] }}</li>
                        @endif
                    </ul>
                @endif
            </div>
            <div>
                <h4 class="font-semibold text-slate-700">{{ __('Product') }}</h4>
                <ul class="mt-3 space-y-2 text-slate-500">
                    <li><a href="{{ route('features') }}" class="hover:text-brand-700">{{ __('Features') }}</a></li>
                    <li><a href="{{ route('pricing') }}" class="hover:text-brand-700">{{ __('Pricing') }}</a></li>
                </ul>
            </div>
            <div>
                <h4 class="font-semibold text-slate-700">{{ __('Tools') }}</h4>
                <ul class="mt-3 space-y-2 text-slate-500">
                    <li><a href="{{ route('tools.index') }}" class="hover:text-brand-700">{{ __('All accounting tools') }}</a></li>
                    <li><a href="{{ route('tools.vat') }}" class="hover:text-brand-700">{{ __('VAT calculator') }}</a></li>
                    <li><a href="{{ route('tools.zakat') }}" class="hover:text-brand-700">{{ __('Zakat calculator') }}</a></li>
                    <li><a href="{{ route('tools.gosi') }}" class="hover:text-brand-700">{{ __('GOSI calculator') }}</a></li>
                    <li><a href="{{ route('tools.invoice-generator') }}" class="hover:text-brand-700">{{ __('Invoice generator') }}</a></li>
                </ul>
            </div>
            <div>
                <h4 class="font-semibold text-slate-700">{{ __('Resources') }}</h4>
                <ul class="mt-3 space-y-2 text-slate-500">
                    <li><a href="{{ route('compliance') }}" class="hover:text-brand-700">{{ __('Compliance') }}</a></li>
                    <li><a href="{{ route('certificates') }}" class="hover:text-brand-700">{{ __('Certificates') }}</a></li>
                    <li><a href="{{ route('glossary') }}" class="hover:text-brand-700">{{ __('Glossary') }}</a></li>
                    @php($__footerPages = \App\Models\CmsPage::query()->where('is_system', false)->where('is_active', true)->where('show_in_footer', true)->orderBy('sort_order')->get())
                    @foreach ($__footerPages as $__footerPage)
                        <li><a href="{{ $__footerPage->publicUrl() }}" class="hover:text-brand-700">{{ $__footerPage->name() }}</a></li>
                    @endforeach
                </ul>
            </div>
            <div>
                <h4 class="font-semibold text-slate-700">{{ __('Company') }}</h4>
                <ul class="mt-3 space-y-2 text-slate-500">
                    <li><a href="{{ route('about') }}" class="hover:text-brand-700">{{ __('About') }}</a></li>
                    <li><a href="{{ route('contact') }}" class="hover:text-brand-700">{{ __('Contact') }}</a></li>
                    <li><a href="{{ route('legal', 'terms') }}" class="hover:text-brand-700">{{ __('Terms') }}</a></li>
                    <li><a href="{{ route('legal', 'privacy') }}" class="hover:text-brand-700">{{ __('Privacy') }}</a></li>
                </ul>
            </div>
        </div>
        @php($__socialSection = \App\Models\CmsSection::query()->where('page', 'contact')->where('type', 'social_links')->where('is_active', true)->with(['items' => fn ($q) => $q->where('is_active', true)])->first())
        @if ($__socialSection && $__socialSection->items->isNotEmpty())
            <div class="border-t border-slate-200 py-5 flex flex-wrap items-center justify-center gap-3">
                @foreach ($__socialSection->items as $__socialItem)
                    <a href="{{ $__socialItem->meta['url'] ?? '#' }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 text-xs font-medium text-slate-500 hover:text-brand-700">
                        @if ($__socialItem->icon)
                            <span>{{ $__socialItem->icon }}</span>
                        @endif
                        {{ $__socialItem->title() }}
                    </a>
                @endforeach
            </div>
        @endif
        <div class="border-t border-slate-200 py-6 text-center text-xs text-slate-400">
            &copy; {{ now()->year }} {{ $__branding['name'] }}. {{ __('All rights reserved.') }}
        </div>
    </footer>
</body>
</html>
