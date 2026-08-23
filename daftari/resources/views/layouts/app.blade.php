<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · Daftari</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 antialiased" x-data="{ mobileNavOpen: false, userMenuOpen: false }">
    @php
        $company = auth()->user()->company;
        $sub = $company?->activeSubscription();
    @endphp

    <div class="flex min-h-screen">
        {{-- Mobile backdrop --}}
        <div x-show="mobileNavOpen" x-transition:enter="transition-opacity ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition-opacity ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" @click="mobileNavOpen = false" class="fixed inset-0 z-40 bg-slate-900/50 lg:hidden print:hidden" style="display: none;"></div>

        <aside
            class="fixed inset-y-0 start-0 z-50 flex w-72 shrink-0 flex-col bg-gradient-to-b from-slate-950 to-slate-900 text-slate-300 transition-transform duration-300 ease-smooth lg:static lg:translate-x-0 print:hidden"
            :class="mobileNavOpen ? 'translate-x-0' : (document.dir === 'rtl' ? 'translate-x-full' : '-translate-x-full')"
        >
            <div class="flex items-center justify-between gap-2 border-b border-white/5 px-6 py-5">
                <a href="{{ route('app.dashboard') }}" class="flex items-center gap-2.5 text-lg font-bold text-white">
                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-brand-400 to-brand-600 text-white shadow-glow">د</span>
                    Daftari
                </a>
                <button type="button" @click="mobileNavOpen = false" class="rounded-lg p-1.5 text-slate-400 hover:bg-white/5 hover:text-white lg:hidden">
                    @include('partials.icon', ['name' => 'close', 'class' => 'h-5 w-5'])
                </button>
            </div>
            <nav class="sidebar-scroll flex-1 space-y-1 overflow-y-auto px-3 py-4 text-sm">
                @include('partials.nav-item', ['route' => 'app.dashboard', 'label' => __('Dashboard'), 'icon' => 'dashboard'])

                @php($salesActive = request()->routeIs('app.quotations.*') || request()->routeIs('app.invoices.*') || request()->routeIs('app.credit-notes.*') || request()->routeIs('app.clients.*') || request()->routeIs('app.salespersons.*'))
                <details class="group" @if($salesActive) open @endif>
                    <summary class="flex cursor-pointer list-none items-center gap-3 rounded-xl px-3 py-2.5 transition-colors {{ $salesActive ? 'text-white' : 'text-slate-400 hover:bg-white/5 hover:text-white' }}">
                        <span class="shrink-0 text-slate-500">@include('partials.icon', ['name' => 'sales', 'class' => 'h-[18px] w-[18px]'])</span>
                        <span class="flex-1 font-medium">{{ __('Sales') }}</span>
                        <span class="text-slate-600 transition-transform duration-200 group-open:rotate-180">@include('partials.icon', ['name' => 'chevron-down', 'class' => 'h-3.5 w-3.5'])</span>
                    </summary>
                    <div class="ms-6 mt-1 space-y-0.5 border-s border-white/5 ps-3">
                        @include('partials.nav-subitem', ['route' => 'app.quotations.index', 'label' => __('Quotations & Proforma Invoices')])
                        @include('partials.nav-subitem', ['route' => 'app.invoices.index', 'label' => __('Invoices')])
                        @include('partials.nav-subitem', ['route' => 'app.credit-notes.index', 'label' => __('Credit notes')])
                        @include('partials.nav-subitem', ['route' => 'app.clients.index', 'label' => __('Customers')])
                        @include('partials.nav-subitem', ['route' => 'app.salespersons.index', 'label' => __('Salespersons')])
                    </div>
                </details>

                @php($itemsActive = request()->routeIs('app.items.*') || request()->routeIs('app.units.*') || request()->routeIs('app.warehouses.*') || request()->routeIs('app.stock-adjustments.*') || request()->routeIs('app.inventory.*'))
                <details class="group" @if($itemsActive) open @endif>
                    <summary class="flex cursor-pointer list-none items-center gap-3 rounded-xl px-3 py-2.5 transition-colors {{ $itemsActive ? 'text-white' : 'text-slate-400 hover:bg-white/5 hover:text-white' }}">
                        <span class="shrink-0 text-slate-500">@include('partials.icon', ['name' => 'items', 'class' => 'h-[18px] w-[18px]'])</span>
                        <span class="flex-1 font-medium">{{ __('Items & Services') }}</span>
                        <span class="text-slate-600 transition-transform duration-200 group-open:rotate-180">@include('partials.icon', ['name' => 'chevron-down', 'class' => 'h-3.5 w-3.5'])</span>
                    </summary>
                    <div class="ms-6 mt-1 space-y-0.5 border-s border-white/5 ps-3">
                        @include('partials.nav-subitem', ['route' => 'app.items.index', 'label' => __('Items')])
                        @include('partials.nav-subitem', ['route' => 'app.units.index', 'label' => __('Units')])
                        @include('partials.nav-subitem', ['route' => 'app.warehouses.index', 'label' => __('Warehouses')])
                        @include('partials.nav-subitem', ['route' => 'app.stock-adjustments.index', 'label' => __('Inventory adjustments')])
                        @include('partials.nav-subitem', ['route' => 'app.inventory.valuation', 'label' => __('Inventory reports')])
                    </div>
                </details>

                @php($purchasesActive = request()->routeIs('app.bills.*') || request()->routeIs('app.purchase-orders.*') || request()->routeIs('app.customs-declarations.*') || request()->routeIs('app.purchase-returns.*') || request()->routeIs('app.suppliers.*') || request()->routeIs('app.expenses.*') || request()->routeIs('app.expense-categories.*'))
                <details class="group" @if($purchasesActive) open @endif>
                    <summary class="flex cursor-pointer list-none items-center gap-3 rounded-xl px-3 py-2.5 transition-colors {{ $purchasesActive ? 'text-white' : 'text-slate-400 hover:bg-white/5 hover:text-white' }}">
                        <span class="shrink-0 text-slate-500">@include('partials.icon', ['name' => 'purchases', 'class' => 'h-[18px] w-[18px]'])</span>
                        <span class="flex-1 font-medium">{{ __('Purchases & Expenses') }}</span>
                        <span class="text-slate-600 transition-transform duration-200 group-open:rotate-180">@include('partials.icon', ['name' => 'chevron-down', 'class' => 'h-3.5 w-3.5'])</span>
                    </summary>
                    <div class="ms-6 mt-1 space-y-0.5 border-s border-white/5 ps-3">
                        @include('partials.nav-subitem', ['route' => 'app.bills.index', 'label' => __('Bills')])
                        @include('partials.nav-subitem', ['route' => 'app.purchase-orders.index', 'label' => __('Purchase orders')])
                        @include('partials.nav-subitem', ['route' => 'app.customs-declarations.index', 'label' => __('Customs Declarations')])
                        @include('partials.nav-subitem', ['route' => 'app.purchase-returns.index', 'label' => __('Purchase returns')])
                        @include('partials.nav-subitem', ['route' => 'app.suppliers.index', 'label' => __('Suppliers')])
                        @include('partials.nav-subitem', ['route' => 'app.expenses.index', 'label' => __('Expenses')])
                    </div>
                </details>

                @include('partials.nav-item', ['route' => 'app.bank-accounts.index', 'label' => __('Cash & Banks'), 'icon' => 'bank'])
                @include('partials.nav-item', ['route' => 'app.projects.index', 'label' => __('Projects'), 'icon' => 'projects'])

                @php($reportsActive = request()->routeIs('app.reports.*'))
                <details class="group" @if($reportsActive) open @endif>
                    <summary class="flex cursor-pointer list-none items-center gap-3 rounded-xl px-3 py-2.5 transition-colors {{ $reportsActive ? 'text-white' : 'text-slate-400 hover:bg-white/5 hover:text-white' }}">
                        <span class="shrink-0 text-slate-500">@include('partials.icon', ['name' => 'reports', 'class' => 'h-[18px] w-[18px]'])</span>
                        <span class="flex-1 font-medium">{{ __('Reports') }}</span>
                        <span class="text-slate-600 transition-transform duration-200 group-open:rotate-180">@include('partials.icon', ['name' => 'chevron-down', 'class' => 'h-3.5 w-3.5'])</span>
                    </summary>
                    <div class="ms-6 mt-1 space-y-0.5 border-s border-white/5 ps-3">
                        @include('partials.nav-subitem', ['route' => 'app.reports.sales', 'label' => __('Sales Report')])
                        @include('partials.nav-subitem', ['route' => 'app.reports.income-statement', 'label' => __('Income Statement (P&L)')])
                        @include('partials.nav-subitem', ['route' => 'app.reports.expenses', 'label' => __('Expense Report')])
                        @include('partials.nav-subitem', ['route' => 'app.reports.cash-flow', 'label' => __('Cash Flow')])
                        @include('partials.nav-subitem', ['route' => 'app.reports.trial-balance', 'label' => __('Trial Balance')])
                        @include('partials.nav-subitem', ['route' => 'app.reports.balance-sheet', 'label' => __('Balance Sheet')])
                        @include('partials.nav-subitem', ['route' => 'app.reports.vat', 'label' => __('VAT Return')])
                        @include('partials.nav-subitem', ['route' => 'app.reports.account-statement', 'label' => __('Account Statement')])
                    </div>
                </details>

                @php($accountingActive = request()->routeIs('app.accounts.*') || request()->routeIs('app.journals.*') || request()->routeIs('app.ledger.*') || request()->routeIs('app.cost-centers.*'))
                <details class="group" @if($accountingActive) open @endif>
                    <summary class="flex cursor-pointer list-none items-center gap-3 rounded-xl px-3 py-2.5 transition-colors {{ $accountingActive ? 'text-white' : 'text-slate-400 hover:bg-white/5 hover:text-white' }}">
                        <span class="shrink-0 text-slate-500">@include('partials.icon', ['name' => 'accounting', 'class' => 'h-[18px] w-[18px]'])</span>
                        <span class="flex-1 font-medium">{{ __('Accounting') }}</span>
                        <span class="text-slate-600 transition-transform duration-200 group-open:rotate-180">@include('partials.icon', ['name' => 'chevron-down', 'class' => 'h-3.5 w-3.5'])</span>
                    </summary>
                    <div class="ms-6 mt-1 space-y-0.5 border-s border-white/5 ps-3">
                        @include('partials.nav-subitem', ['route' => 'app.accounts.index', 'label' => __('Chart of Accounts')])
                        @include('partials.nav-subitem', ['route' => 'app.cost-centers.index', 'label' => __('Cost Centers')])
                        @include('partials.nav-subitem', ['route' => 'app.journals.index', 'label' => __('Journals')])
                        @include('partials.nav-subitem', ['route' => 'app.ledger.index', 'label' => __('Ledger')])
                    </div>
                </details>

                @include('partials.nav-item', ['route' => 'app.zatca.dashboard', 'label' => __('ZATCA'), 'icon' => 'zatca'])
                @include('partials.nav-item', ['route' => 'app.billing.index', 'label' => __('Billing'), 'icon' => 'billing'])
                @if (auth()->user()->isOwner())
                    <div class="my-2 border-t border-white/5"></div>
                    @include('partials.nav-item', ['route' => 'app.team.index', 'label' => __('Team'), 'icon' => 'team'])
                    @include('partials.nav-item', ['route' => 'app.branches.index', 'label' => __('Branches'), 'icon' => 'branches'])
                    @include('partials.nav-item', ['route' => 'app.invoice-templates.index', 'label' => __('Invoice Templates'), 'icon' => 'templates'])
                    @include('partials.nav-item', ['route' => 'app.settings.index', 'label' => __('Settings'), 'icon' => 'settings'])
                @endif
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
             already reserves its w-72 width in this row. Adding matching
             padding on top of that doubled the gap between sidebar and
             content on every screen in the panel. --}}
        <div class="flex min-w-0 flex-1 flex-col">
            <header class="sticky top-0 z-30 flex items-center justify-between gap-4 border-b border-slate-100 bg-white/80 px-4 py-3.5 backdrop-blur-md sm:px-6 print:hidden">
                <div class="flex items-center gap-3">
                    <button type="button" @click="mobileNavOpen = true" class="rounded-lg p-1.5 text-slate-500 hover:bg-slate-100 lg:hidden">
                        @include('partials.icon', ['name' => 'menu', 'class' => 'h-5 w-5'])
                    </button>
                    @if ($company?->logo_path)
                        <img src="{{ Storage::url($company->logo_path) }}" alt="{{ $company->name }}" class="hidden h-8 w-8 rounded-md object-cover border border-slate-200 sm:block">
                    @endif
                    <h1 class="text-base font-semibold text-slate-900 sm:text-lg">@yield('title')</h1>
                </div>
                <div class="flex items-center gap-2 sm:gap-4">
                    <a href="{{ route('locale.switch', app()->getLocale() === 'ar' ? 'en' : 'ar') }}" class="flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-sm font-medium text-slate-500 transition-colors hover:bg-slate-100 hover:text-brand-700">
                        @include('partials.icon', ['name' => 'globe', 'class' => 'h-4 w-4'])
                        {{ app()->getLocale() === 'ar' ? 'EN' : 'AR' }}
                    </a>
                    <div class="relative" @click.outside="userMenuOpen = false">
                        <button type="button" @click="userMenuOpen = !userMenuOpen" class="flex items-center gap-2.5 rounded-lg py-1 ps-1 pe-2 transition-colors hover:bg-slate-100">
                            <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-gradient-to-br from-brand-400 to-brand-600 text-sm font-semibold text-white shadow-soft">
                                {{ mb_substr(auth()->user()->name, 0, 1) }}
                            </span>
                            <span class="hidden text-sm font-medium text-slate-700 sm:block">{{ auth()->user()->name }}</span>
                            <span class="hidden text-slate-400 transition-transform sm:block" :class="userMenuOpen ? 'rotate-180' : ''">@include('partials.icon', ['name' => 'chevron-down', 'class' => 'h-3.5 w-3.5'])</span>
                        </button>
                        <div x-show="userMenuOpen" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 scale-95 -translate-y-1" x-transition:enter-end="opacity-100 scale-100 translate-y-0" x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" class="absolute end-0 top-full z-40 mt-2 w-56 rounded-xl border border-slate-100 bg-white py-2 shadow-card-hover" style="display: none;">
                            <div class="border-b border-slate-50 px-4 py-2.5">
                                <p class="truncate text-sm font-semibold text-slate-800">{{ auth()->user()->name }}</p>
                                <p class="truncate text-xs text-slate-400">{{ auth()->user()->email }}</p>
                            </div>
                            @if (auth()->user()->isOwner())
                                <a href="{{ route('app.settings.index') }}" class="flex items-center gap-2.5 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">
                                    @include('partials.icon', ['name' => 'settings', 'class' => 'h-4 w-4 text-slate-400'])
                                    {{ __('Settings') }}
                                </a>
                            @endif
                            <a href="{{ route('app.billing.index') }}" class="flex items-center gap-2.5 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">
                                @include('partials.icon', ['name' => 'billing', 'class' => 'h-4 w-4 text-slate-400'])
                                {{ __('Billing') }}
                            </a>
                            <form method="POST" action="{{ route('logout') }}" class="border-t border-slate-50 pt-1">
                                @csrf
                                <button class="flex w-full items-center gap-2.5 px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                    @include('partials.icon', ['name' => 'logout', 'class' => 'h-4 w-4'])
                                    {{ __('Log out') }}
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </header>

            @if (session('impersonator_id'))
                <div class="flex items-center justify-between bg-gradient-to-r from-amber-500 to-orange-500 px-6 py-2 text-sm text-white print:hidden">
                    <span class="flex items-center gap-2">
                        @include('partials.icon', ['name' => 'log-in', 'class' => 'h-4 w-4'])
                        {{ __('You are viewing Daftari as :company (support mode).', ['company' => $company?->name]) }}
                    </span>
                    <form method="POST" action="{{ route('stop-impersonating') }}">
                        @csrf
                        <button type="submit" class="font-semibold underline decoration-white/60 underline-offset-2 hover:decoration-white">{{ __('Return to admin') }}</button>
                    </form>
                </div>
            @endif

            @if ($sub && $sub->isTrial())
                <div class="flex items-center justify-between bg-gradient-to-r from-amber-50 to-amber-50/60 border-b border-amber-200 px-6 py-2 text-sm text-amber-800 print:hidden">
                    <span class="flex items-center gap-2">
                        @include('partials.icon', ['name' => 'sparkle', 'class' => 'h-4 w-4'])
                        {{ __('Free trial — :days days left.', ['days' => max(0, now()->diffInDays($sub->current_period_end, false))]) }}
                    </span>
                    <a href="{{ route('app.billing.index') }}" class="font-semibold underline decoration-amber-400 underline-offset-2 hover:text-amber-900">{{ __('Choose a plan') }}</a>
                </div>
            @endif

            <main class="flex-1 px-4 py-6 sm:px-6 sm:py-8 print:p-0">
                @if (session('status'))
                    <div class="mb-6 animate-fade-up rounded-xl border border-brand-200 bg-brand-50 px-4 py-3 text-sm text-brand-800 shadow-soft print:hidden">{{ session('status') }}</div>
                @endif
                @if (session('error'))
                    <div class="mb-6 animate-fade-up rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 shadow-soft print:hidden">{{ session('error') }}</div>
                @endif
                @if ($errors->any())
                    <div class="mb-6 animate-fade-up rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 shadow-soft print:hidden">
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
