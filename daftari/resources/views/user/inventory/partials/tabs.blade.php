<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-900">{{ __('Inventory Reports') }}</h1>
    <p class="text-sm text-slate-500 mt-1">{{ __('Valuation, stock on hand, and profitability') }}</p>
</div>

<div class="flex items-center gap-6 border-b border-slate-200 mb-6 text-sm">
    <a href="{{ route('app.inventory.valuation') }}" class="pb-3 border-b-2 font-semibold {{ request()->routeIs('app.inventory.valuation') ? 'border-brand-600 text-brand-700' : 'border-transparent text-slate-500 hover:text-slate-700' }}">{{ __('Valuation') }}</a>
    <a href="{{ route('app.inventory.stock') }}" class="pb-3 border-b-2 font-semibold {{ request()->routeIs('app.inventory.stock') ? 'border-brand-600 text-brand-700' : 'border-transparent text-slate-500 hover:text-slate-700' }}">{{ __('Stock on hand') }}</a>
    <a href="{{ route('app.inventory.profitability') }}" class="pb-3 border-b-2 font-semibold {{ request()->routeIs('app.inventory.profitability') ? 'border-brand-600 text-brand-700' : 'border-transparent text-slate-500 hover:text-slate-700' }}">{{ __('Profitability') }}</a>
</div>
