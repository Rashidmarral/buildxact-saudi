<div class="flex flex-wrap items-center gap-2 mb-6 text-sm">
    <a href="{{ route('app.accounts.index') }}" class="rounded-lg px-3 py-1.5 {{ request()->routeIs('app.accounts.index') ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100' }}">{{ __('Chart of Accounts') }}</a>
    <a href="{{ route('app.journals.index') }}" class="rounded-lg px-3 py-1.5 {{ request()->routeIs('app.journals.*') ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100' }}">{{ __('Journals') }}</a>
    <a href="{{ route('app.ledger.index') }}" class="rounded-lg px-3 py-1.5 {{ request()->routeIs('app.ledger.index') ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100' }}">{{ __('Ledger') }}</a>
    <a href="{{ route('app.cost-centers.index') }}" class="rounded-lg px-3 py-1.5 {{ request()->routeIs('app.cost-centers.index') ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100' }}">{{ __('Cost Centers') }}</a>
</div>
