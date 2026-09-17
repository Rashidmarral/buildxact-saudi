<div class="flex flex-wrap items-center gap-2 mb-6 text-sm">
    <a href="{{ route('app.bank-accounts.index') }}" class="rounded-lg px-3 py-1.5 {{ request()->routeIs('app.bank-accounts.*') ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100' }}">{{ __('Banks & Treasuries') }}</a>
    <a href="{{ route('app.bank-transactions.index') }}" class="rounded-lg px-3 py-1.5 {{ request()->routeIs('app.bank-transactions.index') ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100' }}">{{ __('Transactions') }}</a>
    <a href="{{ route('app.receipt-vouchers.index') }}" class="rounded-lg px-3 py-1.5 {{ request()->routeIs('app.receipt-vouchers.*') ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100' }}">{{ __('Receipt vouchers') }}</a>
    <a href="{{ route('app.payment-vouchers.index') }}" class="rounded-lg px-3 py-1.5 {{ request()->routeIs('app.payment-vouchers.*') ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100' }}">{{ __('Payment vouchers') }}</a>
    <a href="{{ route('app.bank-transfers.index') }}" class="rounded-lg px-3 py-1.5 {{ request()->routeIs('app.bank-transfers.*') ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100' }}">{{ __('Transfers') }}</a>
</div>
