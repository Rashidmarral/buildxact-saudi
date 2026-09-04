<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ \App\Support\Locales::dir(app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script>
        // Runs synchronously before first paint so the page never flashes
        // light before switching to dark. Honors the saved preference; with
        // none stored yet, falls back to the OS preference for that first
        // visit only — the toggle below always writes an explicit choice.
        (function () {
            try {
                var stored = localStorage.getItem('theme');
                var dark = stored ? stored === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
                document.documentElement.classList.toggle('dark', dark);
            } catch (e) {}
        })();
    </script>
    <title>@yield('title') · Daftari</title>
    <link rel="manifest" href="/manifest.webmanifest">
    <meta name="theme-color" content="#0f9068">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Daftari">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.branding-overrides')
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 antialiased" x-data="{ mobileNavOpen: false, userMenuOpen: false, notificationsOpen: false, darkMode: document.documentElement.classList.contains('dark') }" x-init="$watch('darkMode', value => { document.documentElement.classList.toggle('dark', value); try { localStorage.setItem('theme', value ? 'dark' : 'light'); } catch (e) {} })">
    @php
        $company = auth()->user()->company;
        $sub = $company?->activeSubscription();
        $recentNotifications = auth()->user()->notifications()->latest()->take(8)->get();
        $unreadNotificationsCount = auth()->user()->unreadNotifications()->count();
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

                @php($salesActive = request()->routeIs('app.quotations.*') || request()->routeIs('app.invoices.*') || request()->routeIs('app.recurring-invoices.*') || request()->routeIs('app.credit-notes.*') || request()->routeIs('app.debit-notes.*') || request()->routeIs('app.clients.*') || request()->routeIs('app.salespersons.*'))
                <details class="group" @if($salesActive) open @endif>
                    <summary class="flex cursor-pointer list-none items-center gap-3 rounded-xl px-3 py-2.5 transition-colors {{ $salesActive ? 'text-white' : 'text-slate-400 hover:bg-white/5 hover:text-white' }}">
                        <span class="shrink-0 text-slate-500">@include('partials.icon', ['name' => 'sales', 'class' => 'h-[18px] w-[18px]'])</span>
                        <span class="flex-1 font-medium">{{ __('Sales') }}</span>
                        <span class="text-slate-600 transition-transform duration-200 group-open:rotate-180">@include('partials.icon', ['name' => 'chevron-down', 'class' => 'h-3.5 w-3.5'])</span>
                    </summary>
                    <div class="ms-6 mt-1 space-y-0.5 border-s border-white/5 ps-3">
                        @include('partials.nav-subitem', ['route' => 'app.quotations.index', 'label' => __('Quotations & Proforma Invoices')])
                        @include('partials.nav-subitem', ['route' => 'app.invoices.index', 'label' => __('Invoices')])
                        @include('partials.nav-subitem', ['route' => 'app.recurring-invoices.index', 'label' => __('Recurring Invoices')])
                        @include('partials.nav-subitem', ['route' => 'app.credit-notes.index', 'label' => __('Credit notes')])
                        @include('partials.nav-subitem', ['route' => 'app.debit-notes.index', 'label' => __('Debit notes')])
                        @include('partials.nav-subitem', ['route' => 'app.clients.index', 'label' => __('Customers')])
                        @include('partials.nav-subitem', ['route' => 'app.salespersons.index', 'label' => __('Salespersons')])
                    </div>
                </details>

                @php($itemsActive = request()->routeIs('app.items.*') || request()->routeIs('app.units.*') || request()->routeIs('app.warehouses.*') || request()->routeIs('app.stock-adjustments.*') || request()->routeIs('app.stock-transfers.*') || request()->routeIs('app.item-lots.*') || request()->routeIs('app.inventory.*') || request()->routeIs('app.physical-counts.*'))
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
                        @include('partials.nav-subitem', ['route' => 'app.physical-counts.index', 'label' => __('Physical count')])
                        @include('partials.nav-subitem', ['route' => 'app.stock-transfers.index', 'label' => __('Stock transfers')])
                        @include('partials.nav-subitem', ['route' => 'app.item-lots.index', 'label' => __('Lots & serials')])
                        @include('partials.nav-subitem', ['route' => 'app.inventory.valuation', 'label' => __('Inventory reports')])
                    </div>
                </details>

                @php($purchasesActive = request()->routeIs('app.bills.*') || request()->routeIs('app.purchase-orders.*') || request()->routeIs('app.reorder-suggestions.*') || request()->routeIs('app.customs-declarations.*') || request()->routeIs('app.purchase-returns.*') || request()->routeIs('app.suppliers.*') || request()->routeIs('app.expenses.*') || request()->routeIs('app.expense-categories.*') || request()->routeIs('app.recurring-expenses.*'))
                <details class="group" @if($purchasesActive) open @endif>
                    <summary class="flex cursor-pointer list-none items-center gap-3 rounded-xl px-3 py-2.5 transition-colors {{ $purchasesActive ? 'text-white' : 'text-slate-400 hover:bg-white/5 hover:text-white' }}">
                        <span class="shrink-0 text-slate-500">@include('partials.icon', ['name' => 'purchases', 'class' => 'h-[18px] w-[18px]'])</span>
                        <span class="flex-1 font-medium">{{ __('Purchases & Expenses') }}</span>
                        <span class="text-slate-600 transition-transform duration-200 group-open:rotate-180">@include('partials.icon', ['name' => 'chevron-down', 'class' => 'h-3.5 w-3.5'])</span>
                    </summary>
                    <div class="ms-6 mt-1 space-y-0.5 border-s border-white/5 ps-3">
                        @include('partials.nav-subitem', ['route' => 'app.bills.index', 'label' => __('Bills')])
                        @include('partials.nav-subitem', ['route' => 'app.purchase-orders.index', 'label' => __('Purchase orders')])
                        @include('partials.nav-subitem', ['route' => 'app.reorder-suggestions.index', 'label' => __('Reorder suggestions')])
                        @include('partials.nav-subitem', ['route' => 'app.customs-declarations.index', 'label' => __('Customs Declarations')])
                        @include('partials.nav-subitem', ['route' => 'app.purchase-returns.index', 'label' => __('Purchase returns')])
                        @include('partials.nav-subitem', ['route' => 'app.suppliers.index', 'label' => __('Suppliers')])
                        @include('partials.nav-subitem', ['route' => 'app.expenses.index', 'label' => __('Expenses')])
                        @include('partials.nav-subitem', ['route' => 'app.recurring-expenses.index', 'label' => __('Recurring expenses')])
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
                        @include('partials.nav-subitem', ['route' => 'app.reports.aging', 'label' => __('Aged Receivables/Payables')])
                        @include('partials.nav-subitem', ['route' => 'app.reports.wht-return', 'label' => __('Withholding Tax')])
                    </div>
                </details>

                @php($accountingActive = request()->routeIs('app.accounting-health.*') || request()->routeIs('app.accounts.*') || request()->routeIs('app.journals.*') || request()->routeIs('app.ledger.*') || request()->routeIs('app.cost-centers.*') || request()->routeIs('app.zakat.*') || request()->routeIs('app.fixed-assets.*') || request()->routeIs('app.budgets.*'))
                <details class="group" @if($accountingActive) open @endif>
                    <summary class="flex cursor-pointer list-none items-center gap-3 rounded-xl px-3 py-2.5 transition-colors {{ $accountingActive ? 'text-white' : 'text-slate-400 hover:bg-white/5 hover:text-white' }}">
                        <span class="shrink-0 text-slate-500">@include('partials.icon', ['name' => 'accounting', 'class' => 'h-[18px] w-[18px]'])</span>
                        <span class="flex-1 font-medium">{{ __('Accounting') }}</span>
                        <span class="text-slate-600 transition-transform duration-200 group-open:rotate-180">@include('partials.icon', ['name' => 'chevron-down', 'class' => 'h-3.5 w-3.5'])</span>
                    </summary>
                    <div class="ms-6 mt-1 space-y-0.5 border-s border-white/5 ps-3">
                        @include('partials.nav-subitem', ['route' => 'app.accounting-health.index', 'label' => __('Accounting Health')])
                        @include('partials.nav-subitem', ['route' => 'app.accounts.index', 'label' => __('Chart of Accounts')])
                        @include('partials.nav-subitem', ['route' => 'app.cost-centers.index', 'label' => __('Cost Centers')])
                        @include('partials.nav-subitem', ['route' => 'app.zakat.index', 'label' => __('Zakat Estimate')])
                        @include('partials.nav-subitem', ['route' => 'app.fixed-assets.index', 'label' => __('Fixed Assets')])
                        @include('partials.nav-subitem', ['route' => 'app.budgets.index', 'label' => __('Budgets')])
                        @include('partials.nav-subitem', ['route' => 'app.journals.index', 'label' => __('Journals')])
                        @include('partials.nav-subitem', ['route' => 'app.recurring-journal-entries.index', 'label' => __('Recurring Journal Entries')])
                        @include('partials.nav-subitem', ['route' => 'app.fx-revaluations.index', 'label' => __('FX Revaluations')])
                        @include('partials.nav-subitem', ['route' => 'app.ledger.index', 'label' => __('Ledger')])
                    </div>
                </details>

                @include('partials.nav-item', ['route' => 'app.zatca.dashboard', 'label' => __('ZATCA'), 'icon' => 'zatca'])
                @include('partials.nav-item', ['route' => 'app.audit.index', 'label' => __('Company Audit'), 'icon' => 'shield'])
                @include('partials.nav-item', ['route' => 'app.tickets.index', 'label' => __('Support'), 'icon' => 'support'])
                @include('partials.nav-item', ['route' => 'app.billing.index', 'label' => __('Billing'), 'icon' => 'billing'])
                @if (auth()->user()->isOwner())
                    <div class="my-2 border-t border-white/5"></div>
                    @include('partials.nav-item', ['route' => 'app.team.index', 'label' => __('Team'), 'icon' => 'team'])
                    @include('partials.nav-item', ['route' => 'app.branches.index', 'label' => __('Branches'), 'icon' => 'branches'])
                    @include('partials.nav-item', ['route' => 'app.invoice-templates.index', 'label' => __('Invoice Templates'), 'icon' => 'templates'])
                    @include('partials.nav-item', ['route' => 'app.activity.index', 'label' => __('Activity log'), 'icon' => 'activity'])
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
                <div class="hidden flex-1 max-w-sm md:block" x-data="{
                        open: false, query: '', groups: [], loading: false, timer: null,
                        run() {
                            clearTimeout(this.timer);
                            if (this.query.trim().length < 2) { this.groups = []; this.open = false; return; }
                            this.timer = setTimeout(() => {
                                this.loading = true;
                                fetch('{{ route('app.search') }}?q=' + encodeURIComponent(this.query))
                                    .then(r => r.json())
                                    .then(data => { this.groups = data.groups; this.open = true; this.loading = false; })
                                    .catch(() => { this.loading = false; });
                            }, 250);
                        }
                    }" @click.outside="open = false">
                    <div class="relative">
                        @include('partials.icon', ['name' => 'search', 'class' => 'pointer-events-none absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400'])
                        <input type="search" x-model="query" @input="run()" @focus="if (groups.length) open = true" placeholder="{{ __('Search invoices, clients, items…') }}" class="w-full rounded-lg border border-slate-200 py-1.5 ps-9 pe-3 text-sm focus:border-brand-500 focus:ring-brand-500">
                    </div>
                    <div x-show="open" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 scale-95 -translate-y-1" x-transition:enter-end="opacity-100 scale-100 translate-y-0" x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" class="absolute z-40 mt-2 w-full max-w-sm rounded-xl border border-slate-100 bg-white py-2 shadow-card-hover" style="display: none;">
                        <template x-if="!loading && groups.length === 0">
                            <p class="px-4 py-6 text-center text-sm text-slate-400">{{ __('No results.') }}</p>
                        </template>
                        <template x-for="group in groups" :key="group.label">
                            <div class="mb-1 last:mb-0">
                                <p class="px-4 pt-2 pb-1 text-xs font-semibold text-slate-400" x-text="group.label"></p>
                                <template x-for="item in group.items" :key="item.url">
                                    <a :href="item.url" class="flex items-center justify-between gap-2 px-4 py-2 text-sm hover:bg-slate-50">
                                        <span class="truncate font-medium text-slate-800" x-text="item.title"></span>
                                        <span class="shrink-0 truncate text-xs text-slate-400" x-text="item.subtitle"></span>
                                    </a>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>
                <div class="flex items-center gap-2 sm:gap-4">
                    <button type="button" @click="darkMode = !darkMode" class="rounded-lg p-2 text-slate-500 transition-colors hover:bg-slate-100 hover:text-brand-700" :title="darkMode ? '{{ __('Switch to light mode') }}' : '{{ __('Switch to dark mode') }}'">
                        <span x-show="!darkMode">@include('partials.icon', ['name' => 'moon', 'class' => 'h-4.5 w-4.5'])</span>
                        <span x-show="darkMode" style="display: none;">@include('partials.icon', ['name' => 'sun', 'class' => 'h-4.5 w-4.5'])</span>
                    </button>
                    <a href="{{ route('locale.switch', app()->getLocale() === 'ar' ? 'en' : 'ar') }}" class="flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-sm font-medium text-slate-500 transition-colors hover:bg-slate-100 hover:text-brand-700">
                        @include('partials.icon', ['name' => 'globe', 'class' => 'h-4 w-4'])
                        {{ app()->getLocale() === 'ar' ? 'EN' : 'AR' }}
                    </a>
                    <div class="relative" @click.outside="notificationsOpen = false">
                        <button type="button" @click="notificationsOpen = !notificationsOpen" class="relative rounded-lg p-2 text-slate-500 transition-colors hover:bg-slate-100 hover:text-brand-700">
                            @include('partials.icon', ['name' => 'bell', 'class' => 'h-5 w-5'])
                            @if ($unreadNotificationsCount > 0)
                                <span class="absolute end-1 top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold leading-none text-white">{{ $unreadNotificationsCount > 9 ? '9+' : $unreadNotificationsCount }}</span>
                            @endif
                        </button>
                        <div x-show="notificationsOpen" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 scale-95 -translate-y-1" x-transition:enter-end="opacity-100 scale-100 translate-y-0" x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" class="absolute end-0 top-full z-40 mt-2 w-80 rounded-xl border border-slate-100 bg-white py-2 shadow-card-hover" style="display: none;">
                            <div class="flex items-center justify-between border-b border-slate-50 px-4 py-2.5">
                                <p class="text-sm font-semibold text-slate-800">{{ __('Notifications') }}</p>
                                @if ($unreadNotificationsCount > 0)
                                    <form method="POST" action="{{ route('app.notifications.read-all') }}">
                                        @csrf
                                        <button type="submit" class="text-xs font-semibold text-brand-700 hover:underline">{{ __('Mark all as read') }}</button>
                                    </form>
                                @endif
                            </div>
                            <div class="max-h-96 overflow-y-auto">
                                @forelse ($recentNotifications as $notification)
                                    <form method="POST" action="{{ route('app.notifications.read', $notification->id) }}">
                                        @csrf
                                        <button type="submit" class="flex w-full items-start gap-2.5 px-4 py-2.5 text-start hover:bg-slate-50 {{ $notification->read_at ? '' : 'bg-brand-50/40' }}">
                                            <span class="mt-0.5 inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full {{ $notification->read_at ? 'bg-slate-100 text-slate-400' : 'bg-brand-100 text-brand-600' }}">
                                                @include('partials.icon', ['name' => $notification->data['icon'] ?? 'bell', 'class' => 'h-3.5 w-3.5'])
                                            </span>
                                            <span class="min-w-0 flex-1">
                                                <span class="block truncate text-sm font-medium text-slate-800">{{ $notification->data['title'] ?? '' }}</span>
                                                <span class="block truncate text-xs text-slate-500">{{ $notification->data['body'] ?? '' }}</span>
                                            </span>
                                        </button>
                                    </form>
                                @empty
                                    <p class="px-4 py-6 text-center text-sm text-slate-400">{{ __('No notifications yet.') }}</p>
                                @endforelse
                            </div>
                            <div class="border-t border-slate-50 px-4 pt-2">
                                <a href="{{ route('app.notifications.index') }}" class="block py-1 text-center text-xs font-semibold text-brand-700 hover:underline">{{ __('View all notifications') }}</a>
                            </div>
                        </div>
                    </div>
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

            @if ($company?->isDemo())
                <div class="flex items-center justify-center gap-2 bg-gradient-to-r from-indigo-600 to-violet-600 px-6 py-2 text-sm font-semibold text-white print:hidden">
                    @include('partials.icon', ['name' => 'support', 'class' => 'h-4 w-4'])
                    {{ __('DEMO MODE') }} — {{ __('This is a shared sample account. Deletions, real payments, and ZATCA submissions are disabled.') }}
                </div>
            @endif

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
                {{-- Plan-limit/feature-gate errors (UsageLimitService::friendlyMessage,
                    the various hasReachedPlanLimit() checks across the user
                    controllers, and EnsurePlanFeature's 'feature' key) get
                    their own banner with a direct link to upgrade, rather
                    than sitting as plain text inside the generic error list
                    below where there was previously nothing to click. --}}
                @if ($errors->has('plan_limit') || $errors->has('feature'))
                    <div class="mb-6 animate-fade-up flex flex-col gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 shadow-soft sm:flex-row sm:items-center sm:justify-between print:hidden">
                        <span class="flex items-center gap-2">
                            @include('partials.icon', ['name' => 'sparkle', 'class' => 'h-4 w-4 shrink-0'])
                            {{ $errors->first('plan_limit') ?: $errors->first('feature') }}
                        </span>
                        <a href="{{ route('app.billing.index', ['tab' => 'plans']) }}" class="inline-flex shrink-0 items-center justify-center rounded-lg bg-amber-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-amber-700">{{ __('Upgrade plan') }}</a>
                    </div>
                @endif

                @php($otherErrors = collect($errors->getMessages())->except(['plan_limit', 'feature'])->flatten())
                @if ($otherErrors->isNotEmpty())
                    <div class="mb-6 animate-fade-up rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 shadow-soft print:hidden">
                        <ul class="list-disc ps-4 space-y-1">
                            @foreach ($otherErrors as $error)
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
