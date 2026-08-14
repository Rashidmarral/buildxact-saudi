<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · Daftari</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-800">
    @php
        $company = auth()->user()->company;
        $sub = $company?->activeSubscription();
    @endphp

    <div class="flex min-h-screen">
        <aside class="hidden lg:flex lg:flex-col w-64 shrink-0 bg-slate-900 text-slate-300">
            <a href="{{ route('app.dashboard') }}" class="flex items-center gap-2 font-bold text-lg text-white px-6 py-5 border-b border-slate-800">
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-brand-500 text-white">د</span>
                Daftari
            </a>
            <nav class="flex-1 px-3 py-4 space-y-1 text-sm">
                @php
                    $navItem = function ($route, $label, $icon) {
                        $active = request()->routeIs($route.'*');
                        $classes = $active ? 'bg-brand-600 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white';
                        return '<a href="'.route($route).'" class="flex items-center gap-3 rounded-lg px-3 py-2 '.$classes.'"><span>'.$icon.'</span><span>'.$label.'</span></a>';
                    };
                @endphp
                {!! $navItem('app.dashboard', __('Dashboard'), '📊') !!}
                {!! $navItem('app.quotations.index', __('Quotations'), '📋') !!}
                {!! $navItem('app.invoices.index', __('Invoices'), '🧾') !!}
                {!! $navItem('app.clients.index', __('Clients'), '👥') !!}
                {!! $navItem('app.salespersons.index', __('Salespersons'), '🧑‍💼') !!}
                {!! $navItem('app.items.index', __('Items'), '📦') !!}
                {!! $navItem('app.warehouses.index', __('Inventory'), '🏷️') !!}
                {!! $navItem('app.expenses.index', __('Expenses'), '💳') !!}
                {!! $navItem('app.bills.index', __('Purchases'), '🛒') !!}
                {!! $navItem('app.bank-accounts.index', __('Cash & Banks'), '🏦') !!}
                {!! $navItem('app.reports.vat', __('VAT Report'), '📈') !!}
                {!! $navItem('app.accounts.index', __('Accounting'), '📒') !!}
                {!! $navItem('app.zatca.dashboard', __('ZATCA'), '🔗') !!}
                {!! $navItem('app.billing.index', __('Billing'), '💰') !!}
                @if (auth()->user()->isOwner())
                    {!! $navItem('app.team.index', __('Team'), '🧑‍🤝‍🧑') !!}
                    {!! $navItem('app.branches.index', __('Branches'), '🏢') !!}
                    {!! $navItem('app.invoice-templates.index', __('Invoice Templates'), '🎨') !!}
                    {!! $navItem('app.settings.index', __('Settings'), '⚙️') !!}
                @endif
            </nav>
            <div class="px-3 py-4 border-t border-slate-800">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="w-full flex items-center gap-3 rounded-lg px-3 py-2 text-sm text-slate-300 hover:bg-slate-800 hover:text-white">
                        <span>🚪</span><span>{{ __('Log out') }}</span>
                    </button>
                </form>
            </div>
        </aside>

        <div class="flex-1 flex flex-col min-w-0">
            <header class="flex items-center justify-between bg-white border-b border-slate-100 px-6 py-4">
                <h1 class="text-lg font-semibold text-slate-900">@yield('title')</h1>
                <div class="flex items-center gap-4">
                    <a href="{{ route('locale.switch', app()->getLocale() === 'ar' ? 'en' : 'ar') }}" class="text-sm text-slate-500 hover:text-brand-700">
                        {{ app()->getLocale() === 'ar' ? 'EN' : 'AR' }}
                    </a>
                    <span class="text-sm text-slate-500">{{ auth()->user()->name }}</span>
                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-brand-100 text-brand-700 text-sm font-semibold">
                        {{ mb_substr(auth()->user()->name, 0, 1) }}
                    </span>
                </div>
            </header>

            @if ($sub && $sub->isTrial())
                <div class="bg-amber-50 border-b border-amber-200 text-amber-800 px-6 py-2 text-sm flex items-center justify-between">
                    <span>{{ __('Free trial — :days days left.', ['days' => max(0, now()->diffInDays($sub->current_period_end, false))]) }}</span>
                    <a href="{{ route('app.billing.index') }}" class="font-semibold underline">{{ __('Choose a plan') }}</a>
                </div>
            @endif

            <main class="flex-1 px-6 py-8">
                @if (session('status'))
                    <div class="mb-6 rounded-lg bg-brand-50 border border-brand-200 text-brand-800 px-4 py-3 text-sm">{{ session('status') }}</div>
                @endif
                @if (session('error'))
                    <div class="mb-6 rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">{{ session('error') }}</div>
                @endif
                @if ($errors->any())
                    <div class="mb-6 rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">
                        <ul class="list-disc ps-4 space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>
</body>
</html>
