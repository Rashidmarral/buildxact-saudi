<div class="flex flex-wrap items-center gap-2 mb-6 text-sm">
    <a href="{{ route('admin.sales-center.plan') }}" class="rounded-lg px-3 py-1.5 {{ request()->routeIs('admin.sales-center.plan') ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100' }}">{{ __('90-Day Plan') }}</a>
    <a href="{{ route('admin.sales-center.tools') }}" class="rounded-lg px-3 py-1.5 {{ request()->routeIs('admin.sales-center.tools') ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100' }}">{{ __('Sales Tools') }}</a>
</div>
