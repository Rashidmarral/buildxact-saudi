<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · Daftari Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 antialiased" x-data="{ mobileNavOpen: false }">
    <div class="flex min-h-screen">
        <div x-show="mobileNavOpen" x-transition:enter="transition-opacity ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition-opacity ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" @click="mobileNavOpen = false" class="fixed inset-0 z-40 bg-slate-900/50 lg:hidden" style="display: none;"></div>

        <aside
            class="fixed inset-y-0 start-0 z-50 flex w-72 shrink-0 flex-col bg-gradient-to-b from-slate-950 via-slate-950 to-slate-900 text-slate-300 transition-transform duration-300 ease-smooth lg:static lg:translate-x-0"
            :class="mobileNavOpen ? 'translate-x-0' : (document.dir === 'rtl' ? 'translate-x-full' : '-translate-x-full')"
        >
            <div class="flex items-center justify-between gap-2 border-b border-white/5 px-6 py-5">
                <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2.5 text-lg font-bold text-white">
                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-brand-400 to-brand-600 text-white shadow-glow">د</span>
                    <span>Daftari <span class="text-xs font-normal text-slate-500">Admin</span></span>
                </a>
                <button type="button" @click="mobileNavOpen = false" class="rounded-lg p-1.5 text-slate-400 hover:bg-white/5 hover:text-white lg:hidden">
                    @include('partials.icon', ['name' => 'close', 'class' => 'h-5 w-5'])
                </button>
            </div>
            <nav class="flex-1 space-y-1 px-3 py-4 text-sm">
                @include('partials.nav-item', ['route' => 'admin.dashboard', 'label' => __('Overview'), 'icon' => 'dashboard'])
                @include('partials.nav-item', ['route' => 'admin.companies.index', 'label' => __('Companies'), 'icon' => 'building'])
                @include('partials.nav-item', ['route' => 'admin.plans.index', 'label' => __('Plans'), 'icon' => 'plans'])
                @include('partials.nav-item', ['route' => 'admin.payments.index', 'label' => __('Payments'), 'icon' => 'billing'])
                @include('partials.nav-item', ['route' => 'admin.admins.index', 'label' => __('Admin Users'), 'icon' => 'shield'])
            </nav>
            <div class="border-t border-white/5 p-3">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-slate-400 transition-colors hover:bg-white/5 hover:text-white">
                        @include('partials.icon', ['name' => 'logout', 'class' => 'h-[18px] w-[18px]'])
                        <span>{{ __('Log out') }}</span>
                    </button>
                </form>
            </div>
        </aside>

        {{-- No lg:ps-72 here: the sidebar goes lg:static above, so flexbox
             already reserves its w-72 width in this row. --}}
        <div class="flex min-w-0 flex-1 flex-col">
            <header class="sticky top-0 z-30 flex items-center justify-between gap-4 border-b border-slate-100 bg-white/80 px-4 py-3.5 backdrop-blur-md sm:px-6">
                <div class="flex items-center gap-3">
                    <button type="button" @click="mobileNavOpen = true" class="rounded-lg p-1.5 text-slate-500 hover:bg-slate-100 lg:hidden">
                        @include('partials.icon', ['name' => 'menu', 'class' => 'h-5 w-5'])
                    </button>
                    <h1 class="text-base font-semibold text-slate-900 sm:text-lg">@yield('title')</h1>
                </div>
                <div class="flex items-center gap-2.5">
                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-gradient-to-br from-brand-400 to-brand-600 text-sm font-semibold text-white shadow-soft">
                        {{ mb_substr(auth()->user()->name, 0, 1) }}
                    </span>
                    <span class="hidden text-sm font-medium text-slate-700 sm:block">{{ auth()->user()->name }}</span>
                </div>
            </header>

            <main class="flex-1 px-4 py-6 sm:px-6 sm:py-8">
                @if (session('status'))
                    <div class="mb-6 animate-fade-up rounded-xl border border-brand-200 bg-brand-50 px-4 py-3 text-sm text-brand-800 shadow-soft">{{ session('status') }}</div>
                @endif
                @if ($errors->any())
                    <div class="mb-6 animate-fade-up rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 shadow-soft">
                        <ul class="list-disc ps-4 space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="animate-fade-in">
                    @yield('content')
                </div>
            </main>
        </div>
    </div>
</body>
</html>
