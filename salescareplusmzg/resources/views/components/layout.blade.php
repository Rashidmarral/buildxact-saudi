<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @php
        $__pageTitle = ($title ?? config('company.name')).' — '.config('company.tagline');
        $__pageDescription = $description ?? 'Sales Care Plus MZG is a trusted pharmaceutical distribution company based in Muzaffargarh, Pakistan, supplying quality medicines to pharmacies, hospitals and clinics across South Punjab.';
        $__canonicalUrl = url()->current();
        $__ogImagePath = \App\Models\Setting::get('seo_og_image_path') ?: config('company.logo_path');
        $__ogImageUrl = $__ogImagePath ? asset('storage/'.$__ogImagePath) : null;
    @endphp
    <title>{{ $__pageTitle }}</title>
    <meta name="description" content="{{ $__pageDescription }}">
    <link rel="canonical" href="{{ $__canonicalUrl }}">

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ config('company.name') }}">
    <meta property="og:title" content="{{ $__pageTitle }}">
    <meta property="og:description" content="{{ $__pageDescription }}">
    <meta property="og:url" content="{{ $__canonicalUrl }}">
    @if ($__ogImageUrl)
        <meta property="og:image" content="{{ $__ogImageUrl }}">
    @endif
    <meta name="twitter:card" content="{{ $__ogImageUrl ? 'summary_large_image' : 'summary' }}">
    <meta name="twitter:title" content="{{ $__pageTitle }}">
    <meta name="twitter:description" content="{{ $__pageDescription }}">
    @if ($__ogImageUrl)
        <meta name="twitter:image" content="{{ $__ogImageUrl }}">
    @endif

    <script type="application/ld+json">
        {!! json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'LocalBusiness',
            'name' => config('company.name'),
            'description' => $__pageDescription,
            'url' => url('/'),
            'telephone' => config('company.phone'),
            'email' => config('company.email'),
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => config('company.address'),
            ],
            'image' => $__ogImageUrl,
            'sameAs' => array_values(array_filter(config('company.social', []))),
        ], JSON_UNESCAPED_SLASHES) !!}
    </script>

    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 24 24%22 fill=%22%23173c36%22><rect x=%223%22 y=%223%22 width=%2218%22 height=%2218%22 rx=%225%22/><path d=%22M8 12h8M12 8v8%22 stroke=%22%23e35f38%22 stroke-width=%222%22/></svg>">
    <script>document.documentElement.classList.add('js-ready');</script>
    @php
        $__gaId = \App\Models\Setting::get('analytics_ga_id');
        $__pixelId = \App\Models\Setting::get('analytics_meta_pixel_id');
    @endphp
    @if ($__gaId || $__pixelId)
        <script>
            window.__siteAnalytics = {
                gaId: @json($__gaId),
                pixelId: @json($__pixelId),
            };
        </script>
    @endif
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @if (config('company.theme.primary') || config('company.theme.accent'))
        <style>
            :root {
                @foreach (\App\Support\ColorPalette::ramp(config('company.theme.primary', '#2a9078')) as $shade => $hex)
                    --color-teal-{{ $shade }}: {{ $hex }};
                @endforeach
                @foreach (\App\Support\ColorPalette::ramp(config('company.theme.accent', '#e35f38')) as $shade => $hex)
                    --color-coral-{{ $shade }}: {{ $hex }};
                @endforeach
            }
        </style>
    @endif
</head>
<body class="min-h-screen bg-white text-slate-700 antialiased">

    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:m-3 focus:rounded focus:bg-teal-800 focus:px-4 focus:py-2 focus:text-white">Skip to content</a>

    {{-- Utility bar --}}
    <div class="hidden bg-teal-950 text-xs text-teal-200 sm:block">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-2 sm:px-6 lg:px-8">
            <div class="flex items-center gap-5">
                <a href="tel:{{ config('company.phone') }}" class="flex items-center gap-1.5 hover:text-white">
                    <x-icon name="phone" class="h-3.5 w-3.5" /> {{ config('company.phone') }}
                </a>
                <a href="mailto:{{ config('company.email') }}" class="flex items-center gap-1.5 hover:text-white">
                    <x-icon name="mail" class="h-3.5 w-3.5" /> {{ config('company.email') }}
                </a>
                <span class="hidden items-center gap-1.5 lg:flex">
                    <x-icon name="map-pin" class="h-3.5 w-3.5" /> {{ config('company.address') }}
                </span>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ config('company.social.facebook') }}" class="text-teal-300 hover:text-white" aria-label="Facebook">
                    <svg viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="currentColor"><path d="M13 22v-8h2.7l.4-3.3H13V8.6c0-.9.3-1.6 1.7-1.6H16V4.1C15.6 4 14.6 4 13.5 4 11 4 9.3 5.5 9.3 8.3v2.4H6.6V14h2.7v8h3.7Z"/></svg>
                </a>
                <a href="{{ config('company.social.linkedin') }}" class="text-teal-300 hover:text-white" aria-label="LinkedIn">
                    <svg viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="currentColor"><path d="M4.98 3.5a2 2 0 1 1 0 4 2 2 0 0 1 0-4ZM3 9h4v12H3V9Zm7 0h3.8v1.7h.05c.53-1 1.83-2.05 3.77-2.05 4.03 0 4.78 2.65 4.78 6.1V21h-4v-5.3c0-1.27-.02-2.9-1.77-2.9-1.77 0-2.04 1.38-2.04 2.8V21h-4V9Z"/></svg>
                </a>
                <a href="{{ config('company.social.twitter') }}" class="text-teal-300 hover:text-white" aria-label="Twitter">
                    <svg viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="currentColor"><path d="M22 5.9c-.7.3-1.5.6-2.3.7.8-.5 1.5-1.3 1.8-2.3-.8.5-1.7.8-2.6 1a4.1 4.1 0 0 0-7 3.7A11.6 11.6 0 0 1 3.4 4.6a4.1 4.1 0 0 0 1.3 5.5c-.7 0-1.3-.2-1.9-.5v.1c0 2 1.4 3.6 3.3 4a4.2 4.2 0 0 1-1.9.1 4.1 4.1 0 0 0 3.8 2.9A8.3 8.3 0 0 1 2 18.4a11.6 11.6 0 0 0 6.3 1.9c7.5 0 11.7-6.3 11.7-11.7v-.5c.8-.6 1.5-1.3 2-2.2Z"/></svg>
                </a>
                <a href="{{ config('company.social.instagram') }}" class="text-teal-300 hover:text-white" aria-label="Instagram">
                    <svg viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.75"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1"/></svg>
                </a>
            </div>
        </div>
    </div>

    <header data-site-header class="sticky top-0 z-40 border-b border-slate-200 bg-white/95 backdrop-blur transition-shadow duration-300">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3.5 sm:px-6 lg:px-8">
            <a href="{{ route('home') }}" class="flex items-center gap-2.5 leading-tight">
                @if (config('company.logo_path'))
                    <img src="{{ asset('storage/'.config('company.logo_path')) }}" alt="{{ config('company.short_name') }}" class="h-10 w-auto object-contain">
                @else
                    <span class="block text-xl font-bold tracking-tight text-teal-900">{{ config('company.short_name', config('company.name')) }}</span>
                @endif
                <span class="hidden text-xs text-slate-500 sm:block">{{ config('company.tagline') }}</span>
            </a>

            @php
                $__navItems = \App\Models\NavItem::with('page')->where('is_visible', true)->orderBy('sort_order')->get();
                $navHeaderItems = $__navItems->where('location', 'header');
                $moreItems = $__navItems->where('location', 'header_more');
                $footerCompanyItems = $__navItems->where('location', 'footer_company');
                $footerResourcesItems = $__navItems->where('location', 'footer_resources');
                $footerLegalItems = $__navItems->where('location', 'footer_legal');
                $moreIsActive = $moreItems->contains(fn ($item) => $item->isActive());
            @endphp

            <nav class="hidden items-center gap-7 text-sm font-medium text-slate-600 lg:flex">
                @foreach ($navHeaderItems as $item)
                    @continue(! $item->resolveUrl())
                    @php $isActive = $item->isActive(); @endphp
                    <a href="{{ $item->resolveUrl() }}" @if ($item->open_in_new_tab) target="_blank" rel="noopener" @endif
                       class="group relative py-1 transition hover:text-coral-600 {{ $isActive ? 'text-teal-900 font-semibold' : '' }}">
                        {{ $item->label }}
                        <span class="absolute -bottom-0.5 left-0 h-0.5 rounded-full bg-coral-500 transition-all duration-300 {{ $isActive ? 'w-full' : 'w-0 group-hover:w-full' }}"></span>
                    </a>
                @endforeach

                @if ($moreItems->isNotEmpty())
                    <div class="relative" data-dropdown>
                        <button type="button" data-dropdown-toggle aria-expanded="false" aria-controls="more-menu"
                            class="group flex items-center gap-1 py-1 transition hover:text-coral-600 {{ $moreIsActive ? 'text-teal-900 font-semibold' : '' }}">
                            More
                            <svg viewBox="0 0 24 24" class="h-3.5 w-3.5 transition-transform duration-200" data-dropdown-chevron fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6" /></svg>
                        </button>
                        <div id="more-menu" data-dropdown-panel class="invisible absolute left-1/2 top-full z-30 mt-3 w-56 -translate-x-1/2 translate-y-1 rounded-2xl border border-slate-100 bg-white p-2 opacity-0 shadow-xl transition-all duration-200">
                            @foreach ($moreItems as $item)
                                @continue(! $item->resolveUrl())
                                @php $isActive = $item->isActive(); @endphp
                                <a href="{{ $item->resolveUrl() }}" @if ($item->open_in_new_tab) target="_blank" rel="noopener" @endif
                                   class="block rounded-lg px-3 py-2 text-sm transition-colors duration-150 {{ $isActive ? 'bg-teal-50 font-semibold text-teal-900' : 'text-slate-600 hover:bg-teal-50 hover:text-teal-900' }}">
                                    {{ $item->label }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                @php $isActive = request()->routeIs('contact'); @endphp
                <a href="{{ route('contact') }}"
                   class="group relative py-1 transition hover:text-coral-600 {{ $isActive ? 'text-teal-900 font-semibold' : '' }}">
                    Contact
                    <span class="absolute -bottom-0.5 left-0 h-0.5 rounded-full bg-coral-500 transition-all duration-300 {{ $isActive ? 'w-full' : 'w-0 group-hover:w-full' }}"></span>
                </a>
            </nav>

            <div class="hidden items-center gap-3 lg:flex">
                <a href="{{ route('search') }}" aria-label="Search" class="rounded-full p-2.5 text-slate-500 transition hover:bg-teal-50 hover:text-teal-800">
                    <x-icon name="search" class="h-5 w-5" />
                </a>
                <a href="{{ route('contact') }}" class="inline-flex items-center gap-2 rounded-full bg-teal-900 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition duration-300 hover:-translate-y-0.5 hover:bg-teal-800 hover:shadow-lg">
                    Get a Quote
                </a>
            </div>

            <button id="nav-toggle" type="button" aria-expanded="false" aria-controls="mobile-menu"
                class="inline-flex items-center justify-center rounded-md p-2 text-teal-800 transition hover:bg-teal-50 lg:hidden">
                <span class="sr-only">Toggle navigation</span>
                <x-icon id="nav-icon-open" name="menu" class="h-7 w-7" />
                <x-icon id="nav-icon-close" name="close" class="hidden h-7 w-7" />
            </button>
        </div>

        <nav id="mobile-menu" class="hidden border-t border-slate-100 bg-white px-4 pb-4 pt-2 lg:hidden">
            <div class="flex flex-col gap-1">
                @foreach ($navHeaderItems as $item)
                    @continue(! $item->resolveUrl())
                    <a href="{{ $item->resolveUrl() }}" @if ($item->open_in_new_tab) target="_blank" rel="noopener" @endif
                       class="rounded-md px-3 py-2 text-sm font-medium transition-colors duration-200 {{ $item->isActive() ? 'bg-teal-50 text-teal-900' : 'text-slate-600 hover:bg-teal-50' }}">
                        {{ $item->label }}
                    </a>
                @endforeach
                @if ($moreItems->isNotEmpty())
                    <p class="mt-2 px-3 text-xs font-semibold uppercase tracking-wide text-slate-400">More</p>
                    @foreach ($moreItems as $item)
                        @continue(! $item->resolveUrl())
                        <a href="{{ $item->resolveUrl() }}" @if ($item->open_in_new_tab) target="_blank" rel="noopener" @endif
                           class="rounded-md px-3 py-2 text-sm font-medium transition-colors duration-200 {{ $item->isActive() ? 'bg-teal-50 text-teal-900' : 'text-slate-600 hover:bg-teal-50' }}">
                            {{ $item->label }}
                        </a>
                    @endforeach
                @endif
                <a href="{{ route('search') }}"
                   class="flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium transition-colors duration-200 {{ request()->routeIs('search') ? 'bg-teal-50 text-teal-900' : 'text-slate-600 hover:bg-teal-50' }}">
                    <x-icon name="search" class="h-4 w-4" /> Search
                </a>
                <a href="{{ route('contact') }}"
                   class="rounded-md px-3 py-2 text-sm font-medium transition-colors duration-200 {{ request()->routeIs('contact') ? 'bg-teal-50 text-teal-900' : 'text-slate-600 hover:bg-teal-50' }}">
                    Contact
                </a>
                <a href="{{ route('contact') }}" class="mt-2 rounded-md bg-teal-900 px-3 py-2.5 text-center text-sm font-semibold text-white transition hover:bg-teal-800">
                    Get a Quote
                </a>
            </div>
        </nav>
    </header>

    <main id="main">
        {{ $slot }}
    </main>

    <footer class="bg-teal-950 text-teal-100">
        {{-- Newsletter --}}
        <div class="border-b border-teal-900">
            <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
                <div class="flex flex-col items-center justify-between gap-6 lg:flex-row">
                    <div class="text-center lg:text-left">
                        <h3 class="text-lg font-bold text-white">Stay Updated</h3>
                        <p class="mt-1 text-sm text-teal-300">Get occasional updates on new principals, products and company news.</p>
                    </div>
                    <div class="w-full max-w-md">
                        @if (session('newsletter_status'))
                            <p class="mb-2 flex items-center gap-1.5 text-sm font-medium text-emerald-400">
                                <x-icon name="check-circle" class="h-4 w-4" /> {{ session('newsletter_status') }}
                            </p>
                        @else
                            <form method="POST" action="{{ route('newsletter.store') }}" class="relative flex gap-2">
                                @csrf
                                <x-honeypot-fields />
                                <label for="newsletter-email" class="sr-only">Email address</label>
                                <input type="email" name="email" id="newsletter-email" required placeholder="Your email address"
                                    class="w-full rounded-full border border-teal-800 bg-teal-900/60 px-4 py-2.5 text-sm text-white placeholder:text-teal-400 transition focus:border-coral-400 focus:outline-none focus:ring-1 focus:ring-coral-400">
                                <button type="submit" class="shrink-0 rounded-full bg-coral-500 px-5 py-2.5 text-sm font-semibold text-white transition duration-300 hover:-translate-y-0.5 hover:bg-coral-400">
                                    Subscribe
                                </button>
                            </form>
                            @error('email')
                                <p class="mt-1.5 text-xs text-coral-300">{{ $message }}</p>
                            @enderror
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 gap-10 md:grid-cols-2 lg:grid-cols-4">
                <div>
                    @if (config('company.logo_path'))
                        <img src="{{ asset('storage/'.config('company.logo_path')) }}" alt="{{ config('company.short_name') }}" class="h-9 w-auto object-contain brightness-0 invert">
                    @else
                        <span class="text-xl font-bold text-white">{{ config('company.short_name', config('company.name')) }}</span>
                    @endif
                    <p class="mt-4 text-sm leading-relaxed text-teal-300">
                        {{ config('company.footer_about') }}
                    </p>
                    <div class="mt-5 flex gap-3">
                        <a href="{{ config('company.social.facebook') }}" class="rounded-full border border-teal-700 p-2 text-teal-300 transition hover:border-coral-400 hover:text-white" aria-label="Facebook">
                            <svg viewBox="0 0 24 24" class="h-4 w-4" fill="currentColor"><path d="M13 22v-8h2.7l.4-3.3H13V8.6c0-.9.3-1.6 1.7-1.6H16V4.1C15.6 4 14.6 4 13.5 4 11 4 9.3 5.5 9.3 8.3v2.4H6.6V14h2.7v8h3.7Z"/></svg>
                        </a>
                        <a href="{{ config('company.social.linkedin') }}" class="rounded-full border border-teal-700 p-2 text-teal-300 transition hover:border-coral-400 hover:text-white" aria-label="LinkedIn">
                            <svg viewBox="0 0 24 24" class="h-4 w-4" fill="currentColor"><path d="M4.98 3.5a2 2 0 1 1 0 4 2 2 0 0 1 0-4ZM3 9h4v12H3V9Zm7 0h3.8v1.7h.05c.53-1 1.83-2.05 3.77-2.05 4.03 0 4.78 2.65 4.78 6.1V21h-4v-5.3c0-1.27-.02-2.9-1.77-2.9-1.77 0-2.04 1.38-2.04 2.8V21h-4V9Z"/></svg>
                        </a>
                        <a href="{{ config('company.social.twitter') }}" class="rounded-full border border-teal-700 p-2 text-teal-300 transition hover:border-coral-400 hover:text-white" aria-label="Twitter">
                            <svg viewBox="0 0 24 24" class="h-4 w-4" fill="currentColor"><path d="M22 5.9c-.7.3-1.5.6-2.3.7.8-.5 1.5-1.3 1.8-2.3-.8.5-1.7.8-2.6 1a4.1 4.1 0 0 0-7 3.7A11.6 11.6 0 0 1 3.4 4.6a4.1 4.1 0 0 0 1.3 5.5c-.7 0-1.3-.2-1.9-.5v.1c0 2 1.4 3.6 3.3 4a4.2 4.2 0 0 1-1.9.1 4.1 4.1 0 0 0 3.8 2.9A8.3 8.3 0 0 1 2 18.4a11.6 11.6 0 0 0 6.3 1.9c7.5 0 11.7-6.3 11.7-11.7v-.5c.8-.6 1.5-1.3 2-2.2Z"/></svg>
                        </a>
                        <a href="{{ config('company.social.instagram') }}" class="rounded-full border border-teal-700 p-2 text-teal-300 transition hover:border-coral-400 hover:text-white" aria-label="Instagram">
                            <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.75"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1"/></svg>
                        </a>
                    </div>
                </div>

                <div>
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-teal-400">Company</h3>
                    <ul class="mt-4 space-y-2.5 text-sm text-teal-200">
                        @foreach ($footerCompanyItems as $item)
                            @continue(! $item->resolveUrl())
                            <li><a href="{{ $item->resolveUrl() }}" @if ($item->open_in_new_tab) target="_blank" rel="noopener" @endif class="hover:text-white">{{ $item->label }}</a></li>
                        @endforeach
                    </ul>
                </div>

                <div>
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-teal-400">Resources</h3>
                    <ul class="mt-4 space-y-2.5 text-sm text-teal-200">
                        @foreach ($footerResourcesItems as $item)
                            @continue(! $item->resolveUrl())
                            <li><a href="{{ $item->resolveUrl() }}" @if ($item->open_in_new_tab) target="_blank" rel="noopener" @endif class="hover:text-white">{{ $item->label }}</a></li>
                        @endforeach
                    </ul>
                    <h3 class="mt-6 text-sm font-semibold uppercase tracking-wide text-teal-400">Coverage Areas</h3>
                    <ul class="mt-4 space-y-2.5 text-sm text-teal-200">
                        @foreach (array_slice(config('company.coverage_areas'), 0, 4) as $area)
                            <li>{{ $area }}</li>
                        @endforeach
                        <li>
                            <a href="{{ route('home') }}#coverage" class="inline-flex items-center gap-1 font-medium text-coral-400 hover:text-coral-300">
                                View All <x-icon name="arrow-right" class="h-3 w-3" />
                            </a>
                        </li>
                    </ul>
                </div>

                <div>
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-teal-400">Contact Info</h3>
                    <ul class="mt-4 space-y-3 text-sm text-teal-200">
                        <li class="flex items-start gap-2.5">
                            <x-icon name="map-pin" class="mt-0.5 h-4 w-4 shrink-0 text-coral-400" />
                            <span>{{ config('company.address') }}</span>
                        </li>
                        <li class="flex items-center gap-2.5">
                            <x-icon name="phone" class="h-4 w-4 shrink-0 text-coral-400" />
                            <a href="tel:{{ config('company.phone') }}" class="hover:text-white">{{ config('company.phone') }}</a>
                        </li>
                        <li class="flex items-center gap-2.5">
                            <x-icon name="mail" class="h-4 w-4 shrink-0 text-coral-400" />
                            <a href="mailto:{{ config('company.email') }}" class="hover:text-white">{{ config('company.email') }}</a>
                        </li>
                        <li class="flex items-center gap-2.5">
                            <x-icon name="clock" class="h-4 w-4 shrink-0 text-coral-400" />
                            <span>{{ config('company.hours_short') }}</span>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="mt-12 flex flex-col items-center justify-between gap-4 border-t border-teal-800 pt-6 text-xs text-teal-400 sm:flex-row">
                <p>&copy; {{ date('Y') }} {{ config('company.legal_name') }}. All rights reserved.</p>
                @if ($footerLegalItems->isNotEmpty())
                    <div class="flex flex-wrap items-center justify-center gap-x-4 gap-y-1">
                        @foreach ($footerLegalItems as $item)
                            @continue(! $item->resolveUrl())
                            <a href="{{ $item->resolveUrl() }}" @if ($item->open_in_new_tab) target="_blank" rel="noopener" @endif class="hover:text-white">{{ $item->label }}</a>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </footer>

    @php
        $__whatsappDigits = preg_replace('/\D/', '', (string) config('company.whatsapp'));
    @endphp
    @if ($__whatsappDigits)
        <a href="https://wa.me/{{ $__whatsappDigits }}?text={{ urlencode('Hi '.config('company.short_name', config('company.name')).', I have a question about your products.') }}"
           target="_blank" rel="noopener"
           aria-label="Chat with us on WhatsApp"
           class="group fixed bottom-5 right-5 z-50 flex h-14 w-14 items-center justify-center rounded-full bg-emerald-500 text-white shadow-lg shadow-emerald-500/30 transition duration-300 hover:scale-110 hover:bg-emerald-400">
            <span class="absolute inset-0 rounded-full bg-emerald-500 opacity-75 animate-ping"></span>
            <svg viewBox="0 0 24 24" class="relative h-7 w-7" fill="currentColor"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.79.47 3.47 1.29 4.93L2 22l5.29-1.39a9.9 9.9 0 0 0 4.75 1.21h.01c5.46 0 9.91-4.45 9.91-9.91C21.96 6.45 17.5 2 12.04 2Zm5.8 14.15c-.24.68-1.39 1.32-1.92 1.4-.49.08-1.11.11-1.79-.11-.41-.13-.94-.31-1.62-.6-2.84-1.23-4.69-4.1-4.83-4.29-.14-.19-1.15-1.53-1.15-2.92 0-1.39.73-2.07.99-2.35.26-.28.57-.35.76-.35.19 0 .38 0 .55.01.18.01.41-.07.64.49.24.58.81 1.99.88 2.13.07.14.12.31.02.5-.09.19-.14.31-.28.47-.14.16-.29.36-.42.48-.14.13-.28.28-.12.55.16.28.71 1.17 1.53 1.9 1.05.94 1.94 1.23 2.21 1.37.28.14.44.12.6-.07.16-.19.68-.79.87-1.06.18-.28.37-.23.62-.14.26.09 1.63.77 1.91.91.28.14.47.21.54.33.07.12.07.68-.17 1.36Z"/></svg>
        </a>
    @endif

    @if (\App\Models\Setting::get('cookie_banner_enabled', '1') === '1')
        @php
            $__privacyPage = \App\Models\Page::where('slug', 'privacy-policy')->where('is_published', true)->first();
        @endphp
        <div id="cookie-consent-banner" class="fixed inset-x-0 bottom-0 z-50 hidden">
            <div class="mx-auto max-w-4xl p-4">
                <div class="flex flex-col items-center gap-4 rounded-2xl bg-teal-950 p-5 shadow-2xl ring-1 ring-white/10 sm:flex-row">
                    <p class="text-sm leading-relaxed text-teal-200">
                        {{ \App\Models\Setting::get('cookie_banner_text', "We use cookies to improve your experience on our site and understand how it's used.") }}
                        @if ($__privacyPage)
                            <a href="{{ route('page.show', $__privacyPage->slug) }}" class="font-medium text-coral-400 underline hover:text-coral-300">Learn more</a>
                        @endif
                    </p>
                    <div class="flex shrink-0 items-center gap-3">
                        <button type="button" id="cookie-consent-decline" class="rounded-full border border-white/20 px-4 py-2 text-sm font-medium text-teal-200 transition hover:bg-white/10">
                            Decline
                        </button>
                        <button type="button" id="cookie-consent-accept" class="rounded-full bg-coral-500 px-5 py-2 text-sm font-semibold text-white transition hover:bg-coral-400">
                            Accept
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

</body>
</html>
