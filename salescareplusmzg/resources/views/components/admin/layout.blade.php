<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Dashboard' }} — Admin — {{ config('company.name') }}</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 24 24%22 fill=%22%23173c36%22><rect x=%223%22 y=%223%22 width=%2218%22 height=%2218%22 rx=%225%22/><path d=%22M8 12h8M12 8v8%22 stroke=%22%23e35f38%22 stroke-width=%222%22/></svg>">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-700 antialiased">

    @php
        $adminNav = [
            'admin.dashboard' => ['Dashboard', 'globe'],
            'admin.pages.index' => ['Pages', 'file-check'],
            'admin.content-items.groups' => ['Page Content', 'file-check'],
            'admin.nav-items.index' => ['Navigation', 'menu'],
            'admin.settings.edit' => ['Site Settings', 'badge-check'],
        ];
        $contentNav = [
            'admin.product-categories.index' => ['Product Categories', 'pill'],
            'admin.products.index' => ['Products', 'pill'],
            'admin.principals.index' => ['Principals', 'building'],
            'admin.client-logos.index' => ['Our Clients', 'building'],
            'admin.testimonials.index' => ['Testimonials', 'quote'],
            'admin.certifications.index' => ['Certifications', 'badge-check'],
            'admin.team-members.index' => ['Team Members', 'users'],
            'admin.faqs.index' => ['FAQs', 'quote'],
            'admin.gallery-images.index' => ['Gallery', 'building'],
        ];
        $leadsNav = [
            'admin.contact-messages.index' => ['Contact Messages', 'mail'],
            'admin.newsletter-subscribers.index' => ['Newsletter Subscribers', 'send'],
        ];
    @endphp

    <div class="flex min-h-screen">
        <aside class="hidden w-64 shrink-0 border-r border-slate-200 bg-white lg:block">
            <div class="flex h-16 items-center gap-2 border-b border-slate-100 px-5">
                @if (config('company.logo_path'))
                    <img src="{{ asset('storage/'.config('company.logo_path')) }}" alt="{{ config('company.short_name') }}" class="h-8 w-auto object-contain">
                @else
                    <span class="text-lg font-bold text-teal-900">{{ config('company.short_name', config('company.name')) }}</span>
                @endif
                <span class="rounded-full bg-teal-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-teal-700">Admin</span>
            </div>
            <nav class="space-y-6 px-3 py-6 text-sm">
                <div>
                    <p class="px-3 text-[11px] font-semibold uppercase tracking-wide text-slate-400">General</p>
                    <div class="mt-1 space-y-0.5">
                        @foreach ($adminNav as $route => [$label, $icon])
                            <a href="{{ route($route) }}" class="flex items-center gap-2.5 rounded-lg px-3 py-2 font-medium transition {{ request()->routeIs($route) || request()->routeIs(str($route)->before('.index').'.*') || request()->routeIs('admin.content-items.*') && $route === 'admin.content-items.groups' ? 'bg-teal-50 text-teal-900' : 'text-slate-600 hover:bg-slate-50' }}">
                                <x-icon :name="$icon" class="h-4 w-4" /> {{ $label }}
                            </a>
                        @endforeach
                    </div>
                </div>
                <div>
                    <p class="px-3 text-[11px] font-semibold uppercase tracking-wide text-slate-400">Content</p>
                    <div class="mt-1 space-y-0.5">
                        @foreach ($contentNav as $route => [$label, $icon])
                            <a href="{{ route($route) }}" class="flex items-center gap-2.5 rounded-lg px-3 py-2 font-medium transition {{ request()->routeIs($route) || request()->routeIs(str($route)->before('.index').'.*') ? 'bg-teal-50 text-teal-900' : 'text-slate-600 hover:bg-slate-50' }}">
                                <x-icon :name="$icon" class="h-4 w-4" /> {{ $label }}
                            </a>
                        @endforeach
                    </div>
                </div>
                <div>
                    <p class="px-3 text-[11px] font-semibold uppercase tracking-wide text-slate-400">Leads</p>
                    <div class="mt-1 space-y-0.5">
                        @foreach ($leadsNav as $route => [$label, $icon])
                            <a href="{{ route($route) }}" class="flex items-center gap-2.5 rounded-lg px-3 py-2 font-medium transition {{ request()->routeIs($route) ? 'bg-teal-50 text-teal-900' : 'text-slate-600 hover:bg-slate-50' }}">
                                <x-icon :name="$icon" class="h-4 w-4" /> {{ $label }}
                            </a>
                        @endforeach
                    </div>
                </div>
                <div class="border-t border-slate-100 pt-4">
                    <a href="{{ route('admin.profile.edit') }}" class="flex items-center gap-2.5 rounded-lg px-3 py-2 font-medium {{ request()->routeIs('admin.profile.edit') ? 'bg-teal-50 text-teal-900' : 'text-slate-500 hover:bg-slate-50' }}">
                        <x-icon name="users" class="h-4 w-4" /> My Profile
                    </a>
                    <a href="{{ route('home') }}" target="_blank" class="flex items-center gap-2.5 rounded-lg px-3 py-2 font-medium text-slate-500 hover:bg-slate-50">
                        <x-icon name="arrow-right" class="h-4 w-4" /> View Website
                    </a>
                    <form method="POST" action="{{ route('admin.logout') }}">
                        @csrf
                        <button type="submit" class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-left font-medium text-slate-500 hover:bg-slate-50">
                            <x-icon name="close" class="h-4 w-4" /> Log Out
                        </button>
                    </form>
                </div>
            </nav>
        </aside>

        <div class="flex-1">
            <header class="flex items-center justify-between border-b border-slate-200 bg-white px-4 py-3 lg:px-8">
                <h1 class="text-lg font-bold text-teal-900">{{ $title ?? 'Dashboard' }}</h1>
                <a href="{{ route('admin.profile.edit') }}" class="text-sm text-slate-500 hover:text-teal-700">{{ auth()->user()->name }}</a>
            </header>

            <main class="px-4 py-6 lg:px-8 lg:py-8">
                @if (session('status'))
                    <div class="mb-6 flex items-center gap-2 rounded-xl bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
                        <x-icon name="check-circle" class="h-5 w-5 shrink-0" /> {{ session('status') }}
                    </div>
                @endif

                {{ $slot }}
            </main>
        </div>
    </div>

</body>
</html>
