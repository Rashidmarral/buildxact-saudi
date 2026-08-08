<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? config('company.name') }} — {{ config('company.tagline') }}</title>
    <meta name="description" content="{{ $description ?? 'Sales Care Plus MZG is a trusted pharmaceutical distribution company based in Muzaffargarh, Pakistan, supplying quality medicines to pharmacies, hospitals and clinics across the region.' }}">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 24 24%22 fill=%22%23277a3a%22><path d=%22M6 20C4 12 8 4 20 4c0 12-8 16-16 16Z%22/></svg>">
    <script>document.documentElement.classList.add('js-ready');</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-white text-stone-800 antialiased">

    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:m-3 focus:rounded focus:bg-leaf-700 focus:px-4 focus:py-2 focus:text-white">Skip to content</a>

    <header data-site-header class="sticky top-0 z-40 border-b border-leaf-100 bg-white/90 backdrop-blur transition-shadow duration-300">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3 sm:px-6 lg:px-8">
            <a href="{{ route('home') }}" class="group flex items-center gap-2.5">
                <span class="flex h-10 w-10 items-center justify-center rounded-full bg-leaf-600 text-white transition-transform duration-300 group-hover:rotate-12">
                    <x-icon name="leaf" class="h-6 w-6" />
                </span>
                <span class="leading-tight">
                    <span class="block font-semibold text-leaf-900">Sales Care Plus <span class="text-leaf-600">MZG</span></span>
                    <span class="block text-xs text-stone-500">Medicine Distribution Company</span>
                </span>
            </a>

            <nav class="hidden items-center gap-7 text-sm font-medium text-stone-700 lg:flex">
                @php
                    $navLinks = [
                        'home' => 'Home',
                        'about' => 'About Us',
                        'products.index' => 'Products',
                        'services' => 'Services',
                        'quality' => 'Quality',
                        'gallery' => 'Gallery',
                        'careers' => 'Careers',
                    ];
                @endphp
                @foreach ($navLinks as $routeName => $label)
                    @php $isActive = request()->routeIs($routeName) || request()->routeIs($routeName.'.*'); @endphp
                    <a href="{{ route($routeName) }}"
                       class="group relative py-1 transition hover:text-leaf-700 {{ $isActive ? 'text-leaf-700 font-semibold' : '' }}">
                        {{ $label }}
                        <span class="absolute -bottom-0.5 left-0 h-0.5 rounded-full bg-leaf-600 transition-all duration-300 {{ $isActive ? 'w-full' : 'w-0 group-hover:w-full' }}"></span>
                    </a>
                @endforeach
            </nav>

            <div class="hidden lg:block">
                <a href="{{ route('contact') }}" class="inline-flex items-center gap-2 rounded-full bg-leaf-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition duration-300 hover:-translate-y-0.5 hover:bg-leaf-700 hover:shadow-lg">
                    Contact Us
                    <x-icon name="arrow-right" class="h-4 w-4 transition-transform duration-300 group-hover:translate-x-1" />
                </a>
            </div>

            <button id="nav-toggle" type="button" aria-expanded="false" aria-controls="mobile-menu"
                class="inline-flex items-center justify-center rounded-md p-2 text-leaf-700 transition hover:bg-leaf-50 lg:hidden">
                <span class="sr-only">Toggle navigation</span>
                <x-icon id="nav-icon-open" name="menu" class="h-7 w-7" />
                <x-icon id="nav-icon-close" name="close" class="hidden h-7 w-7" />
            </button>
        </div>

        <nav id="mobile-menu" class="hidden border-t border-leaf-100 bg-white px-4 pb-4 pt-2 lg:hidden">
            <div class="flex flex-col gap-1">
                @foreach ($navLinks as $routeName => $label)
                    <a href="{{ route($routeName) }}"
                       class="rounded-md px-3 py-2 text-sm font-medium transition-colors duration-200 {{ request()->routeIs($routeName) || request()->routeIs($routeName.'.*') ? 'bg-leaf-50 text-leaf-700' : 'text-stone-700 hover:bg-leaf-50' }}">
                        {{ $label }}
                    </a>
                @endforeach
                <a href="{{ route('contact') }}" class="mt-2 rounded-md bg-leaf-600 px-3 py-2.5 text-center text-sm font-semibold text-white transition hover:bg-leaf-700">
                    Contact Us
                </a>
            </div>
        </nav>
    </header>

    <main id="main">
        {{ $slot }}
    </main>

    <footer class="bg-leaf-950 text-leaf-100">
        <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 gap-10 md:grid-cols-2 lg:grid-cols-4">
                <div>
                    <div class="flex items-center gap-2.5">
                        <span class="flex h-10 w-10 items-center justify-center rounded-full bg-leaf-600 text-white">
                            <x-icon name="leaf" class="h-6 w-6" />
                        </span>
                        <span class="font-semibold text-white">Sales Care Plus MZG</span>
                    </div>
                    <p class="mt-4 text-sm leading-relaxed text-leaf-200">
                        A Muzaffargarh-based pharmaceutical distribution company supplying quality-assured
                        medicines to pharmacies, hospitals and clinics across South Punjab since {{ config('company.founded_year') }}.
                    </p>
                    <div class="mt-5 flex gap-3">
                        <a href="{{ config('company.social.facebook') }}" class="rounded-full border border-leaf-700 p-2 text-leaf-200 transition hover:border-leaf-400 hover:text-white" aria-label="Facebook">
                            <svg viewBox="0 0 24 24" class="h-4 w-4" fill="currentColor"><path d="M13 22v-8h2.7l.4-3.3H13V8.6c0-.9.3-1.6 1.7-1.6H16V4.1C15.6 4 14.6 4 13.5 4 11 4 9.3 5.5 9.3 8.3v2.4H6.6V14h2.7v8h3.7Z"/></svg>
                        </a>
                        <a href="{{ config('company.social.instagram') }}" class="rounded-full border border-leaf-700 p-2 text-leaf-200 transition hover:border-leaf-400 hover:text-white" aria-label="Instagram">
                            <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.75"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1"/></svg>
                        </a>
                        <a href="{{ config('company.social.linkedin') }}" class="rounded-full border border-leaf-700 p-2 text-leaf-200 transition hover:border-leaf-400 hover:text-white" aria-label="LinkedIn">
                            <svg viewBox="0 0 24 24" class="h-4 w-4" fill="currentColor"><path d="M4.98 3.5a2 2 0 1 1 0 4 2 2 0 0 1 0-4ZM3 9h4v12H3V9Zm7 0h3.8v1.7h.05c.53-1 1.83-2.05 3.77-2.05 4.03 0 4.78 2.65 4.78 6.1V21h-4v-5.3c0-1.27-.02-2.9-1.77-2.9-1.77 0-2.04 1.38-2.04 2.8V21h-4V9Z"/></svg>
                        </a>
                    </div>
                </div>

                <div>
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-leaf-300">Quick Links</h3>
                    <ul class="mt-4 space-y-2.5 text-sm text-leaf-200">
                        <li><a href="{{ route('about') }}" class="hover:text-white">About Us</a></li>
                        <li><a href="{{ route('products.index') }}" class="hover:text-white">Our Products</a></li>
                        <li><a href="{{ route('services') }}" class="hover:text-white">Services</a></li>
                        <li><a href="{{ route('quality') }}" class="hover:text-white">Quality &amp; Certifications</a></li>
                        <li><a href="{{ route('careers') }}" class="hover:text-white">Careers</a></li>
                    </ul>
                </div>

                <div>
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-leaf-300">Product Categories</h3>
                    <ul class="mt-4 space-y-2.5 text-sm text-leaf-200">
                        <li><a href="{{ route('products.index') }}?category=analgesics-pain-management" class="hover:text-white">Analgesics &amp; Pain</a></li>
                        <li><a href="{{ route('products.index') }}?category=antibiotics-anti-infectives" class="hover:text-white">Antibiotics</a></li>
                        <li><a href="{{ route('products.index') }}?category=cardiovascular-care" class="hover:text-white">Cardiovascular Care</a></li>
                        <li><a href="{{ route('products.index') }}?category=vitamins-minerals-supplements" class="hover:text-white">Vitamins &amp; Supplements</a></li>
                    </ul>
                </div>

                <div>
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-leaf-300">Get in Touch</h3>
                    <ul class="mt-4 space-y-3 text-sm text-leaf-200">
                        <li class="flex items-start gap-2.5">
                            <x-icon name="map-pin" class="mt-0.5 h-4 w-4 shrink-0 text-leaf-400" />
                            <span>{{ config('company.address') }}</span>
                        </li>
                        <li class="flex items-center gap-2.5">
                            <x-icon name="phone" class="h-4 w-4 shrink-0 text-leaf-400" />
                            <a href="tel:{{ config('company.phone') }}" class="hover:text-white">{{ config('company.phone') }}</a>
                        </li>
                        <li class="flex items-center gap-2.5">
                            <x-icon name="mail" class="h-4 w-4 shrink-0 text-leaf-400" />
                            <a href="mailto:{{ config('company.email') }}" class="hover:text-white">{{ config('company.email') }}</a>
                        </li>
                        <li class="flex items-center gap-2.5">
                            <x-icon name="clock" class="h-4 w-4 shrink-0 text-leaf-400" />
                            <span>{{ config('company.hours') }}</span>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="mt-12 flex flex-col items-center justify-between gap-4 border-t border-leaf-800 pt-6 text-xs text-leaf-300 sm:flex-row">
                <p>&copy; {{ date('Y') }} {{ config('company.legal_name') }}. All rights reserved.</p>
                <p>{{ config('company.domain') }} &middot; Muzaffargarh, Punjab, Pakistan</p>
            </div>
        </div>
    </footer>

</body>
</html>
