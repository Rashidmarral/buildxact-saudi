<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? config('company.name') }} — {{ config('company.tagline') }}</title>
    <meta name="description" content="{{ $description ?? 'Sales Care Plus MZG is a trusted pharmaceutical distribution company based in Muzaffargarh, Pakistan, supplying quality medicines to pharmacies, hospitals and clinics across South Punjab.' }}">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 24 24%22 fill=%22%23173c36%22><rect x=%223%22 y=%223%22 width=%2218%22 height=%2218%22 rx=%225%22/><path d=%22M8 12h8M12 8v8%22 stroke=%22%23e35f38%22 stroke-width=%222%22/></svg>">
    <script>document.documentElement.classList.add('js-ready');</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
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
            <a href="{{ route('home') }}" class="leading-tight">
                <span class="block text-xl font-bold tracking-tight text-teal-900">SalesCare<span class="text-coral-500">+</span></span>
                <span class="block text-xs text-slate-500">{{ config('company.tagline') }}</span>
            </a>

            @php
                $navLinks = [
                    'home' => 'Home',
                    'about' => 'About',
                    'principals' => 'Principals',
                    'catalog.index' => 'Catalog',
                    'services' => 'Services',
                ];
                $moreLinks = [
                    'quality' => 'Quality &amp; Certifications',
                    'gallery' => 'Gallery',
                    'careers' => 'Careers',
                    'faq' => 'FAQs',
                ];
                $moreIsActive = collect(array_keys($moreLinks))->contains(fn ($r) => request()->routeIs($r) || request()->routeIs($r.'.*'));
            @endphp

            <nav class="hidden items-center gap-7 text-sm font-medium text-slate-600 lg:flex">
                @foreach ($navLinks as $routeName => $label)
                    @php $isActive = request()->routeIs($routeName) || request()->routeIs($routeName.'.*'); @endphp
                    <a href="{{ route($routeName) }}"
                       class="group relative py-1 transition hover:text-coral-600 {{ $isActive ? 'text-teal-900 font-semibold' : '' }}">
                        {{ $label }}
                        <span class="absolute -bottom-0.5 left-0 h-0.5 rounded-full bg-coral-500 transition-all duration-300 {{ $isActive ? 'w-full' : 'w-0 group-hover:w-full' }}"></span>
                    </a>
                @endforeach

                <div class="relative" data-dropdown>
                    <button type="button" data-dropdown-toggle aria-expanded="false" aria-controls="more-menu"
                        class="group flex items-center gap-1 py-1 transition hover:text-coral-600 {{ $moreIsActive ? 'text-teal-900 font-semibold' : '' }}">
                        More
                        <svg viewBox="0 0 24 24" class="h-3.5 w-3.5 transition-transform duration-200" data-dropdown-chevron fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6" /></svg>
                    </button>
                    <div id="more-menu" data-dropdown-panel class="invisible absolute left-1/2 top-full z-30 mt-3 w-56 -translate-x-1/2 translate-y-1 rounded-2xl border border-slate-100 bg-white p-2 opacity-0 shadow-xl transition-all duration-200">
                        @foreach ($moreLinks as $routeName => $label)
                            @php $isActive = request()->routeIs($routeName) || request()->routeIs($routeName.'.*'); @endphp
                            <a href="{{ route($routeName) }}"
                               class="block rounded-lg px-3 py-2 text-sm transition-colors duration-150 {{ $isActive ? 'bg-teal-50 font-semibold text-teal-900' : 'text-slate-600 hover:bg-teal-50 hover:text-teal-900' }}">
                                {!! $label !!}
                            </a>
                        @endforeach
                    </div>
                </div>

                @php $isActive = request()->routeIs('contact'); @endphp
                <a href="{{ route('contact') }}"
                   class="group relative py-1 transition hover:text-coral-600 {{ $isActive ? 'text-teal-900 font-semibold' : '' }}">
                    Contact
                    <span class="absolute -bottom-0.5 left-0 h-0.5 rounded-full bg-coral-500 transition-all duration-300 {{ $isActive ? 'w-full' : 'w-0 group-hover:w-full' }}"></span>
                </a>
            </nav>

            <div class="hidden lg:block">
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
                @foreach ($navLinks as $routeName => $label)
                    <a href="{{ route($routeName) }}"
                       class="rounded-md px-3 py-2 text-sm font-medium transition-colors duration-200 {{ request()->routeIs($routeName) || request()->routeIs($routeName.'.*') ? 'bg-teal-50 text-teal-900' : 'text-slate-600 hover:bg-teal-50' }}">
                        {{ $label }}
                    </a>
                @endforeach
                <p class="mt-2 px-3 text-xs font-semibold uppercase tracking-wide text-slate-400">More</p>
                @foreach ($moreLinks as $routeName => $label)
                    <a href="{{ route($routeName) }}"
                       class="rounded-md px-3 py-2 text-sm font-medium transition-colors duration-200 {{ request()->routeIs($routeName) || request()->routeIs($routeName.'.*') ? 'bg-teal-50 text-teal-900' : 'text-slate-600 hover:bg-teal-50' }}">
                        {!! $label !!}
                    </a>
                @endforeach
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
                            <form method="POST" action="{{ route('newsletter.store') }}" class="flex gap-2">
                                @csrf
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
                    <span class="text-xl font-bold text-white">SalesCare<span class="text-coral-400">+</span></span>
                    <p class="mt-4 text-sm leading-relaxed text-teal-300">
                        Leading pharmaceutical distribution organisation serving healthcare providers
                        across South Punjab, headquartered in Muzaffargarh since {{ config('company.founded_year') }}.
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
                        <li><a href="{{ route('about') }}" class="hover:text-white">About</a></li>
                        <li><a href="{{ route('principals') }}" class="hover:text-white">Principals</a></li>
                        <li><a href="{{ route('services') }}" class="hover:text-white">Services</a></li>
                        <li><a href="{{ route('quality') }}" class="hover:text-white">Quality &amp; Certifications</a></li>
                        <li><a href="{{ route('careers') }}" class="hover:text-white">Careers</a></li>
                    </ul>
                </div>

                <div>
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-teal-400">Resources</h3>
                    <ul class="mt-4 space-y-2.5 text-sm text-teal-200">
                        <li><a href="{{ route('catalog.index') }}" class="hover:text-white">Catalog</a></li>
                        <li><a href="{{ route('gallery') }}" class="hover:text-white">Gallery</a></li>
                        <li><a href="{{ route('faq') }}" class="hover:text-white">FAQs</a></li>
                        <li><a href="{{ route('contact') }}" class="hover:text-white">Contact</a></li>
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
                <p>Designed with <span class="text-red-400">&hearts;</span> for healthcare excellence</p>
            </div>
        </div>
    </footer>

</body>
</html>
