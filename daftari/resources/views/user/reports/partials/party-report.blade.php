{{--
    Shared body for Customer/Supplier Report — both views pass $type
    ('customer' or 'supplier'), $routeName, and bilingual labels, and
    include this. Controller side: ReportController::customers()/
    suppliers() both build their $rows the same way via partyRows().
--}}
@php($isCustomer = $type === 'customer')

<div class="flex items-start justify-between mb-6">
    <div>
        <h2 class="text-lg font-semibold text-slate-900">{{ $titleLabel }}</h2>
        <p class="text-sm text-slate-500 mt-1">{{ $subtitleLabel }}</p>
    </div>
    <div class="flex gap-2">
        <a href="{{ route($routeName, array_merge(request()->query(), ['export' => 'csv'])) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download CSV') }}</a>
        <a href="{{ route($routeName, array_merge(request()->query(), ['export' => 'pdf'])) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download PDF') }}</a>
    </div>
</div>

@include('user.reports.partials.period-selector', ['period' => $period])

<div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-slate-100 p-4">
        <p class="text-xs text-slate-400">{{ __('Total outstanding') }}</p>
        <p class="text-lg font-bold text-amber-600 mt-1">{{ \App\Support\Money::format($totalOutstanding) }}</p>
    </div>
    <div class="bg-white rounded-xl border border-slate-100 p-4">
        <p class="text-xs text-slate-400">{{ $isCustomer ? __('Collected this period') : __('Paid this period') }}</p>
        <p class="text-lg font-bold text-emerald-600 mt-1">{{ \App\Support\Money::format($totalCollected) }}</p>
    </div>
    <div class="bg-white rounded-xl border border-slate-100 p-4">
        <p class="text-xs text-slate-400">{{ $isCustomer ? __('Invoiced this period') : __('Billed this period') }}</p>
        <p class="text-lg font-bold text-slate-900 mt-1">{{ \App\Support\Money::format($totalInvoiced) }}</p>
    </div>
    <div class="bg-white rounded-xl border border-slate-100 p-4">
        <p class="text-xs text-slate-400">{{ $isCustomer ? __('Top customer') : __('Top supplier') }}</p>
        <p class="text-sm font-bold text-slate-900 mt-1 truncate">{{ $topParty ? ($topParty['party']->display_name ?? $topParty['party']->name) : '—' }}</p>
    </div>
</div>

<form method="GET" class="flex items-center gap-2 mb-4">
    @foreach (array_diff_key(request()->query(), ['search' => '']) as $name => $value)
        @if ($name !== 'search')
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
        @endif
    @endforeach
    <input type="search" name="search" value="{{ request()->query('search') }}" placeholder="{{ $isCustomer ? __('Search customers…') : __('Search suppliers…') }}" class="w-64 rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
    <button type="submit" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:border-slate-300">{{ __('Search') }}</button>
</form>

@php($sortLink = fn ($col) => route($routeName, array_merge(request()->query(), ['sort' => $col, 'direction' => (request()->query('sort') === $col && request()->query('direction', 'desc') === 'desc') ? 'asc' : 'desc'])))

<div class="bg-white rounded-xl border border-slate-100 overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-slate-500 border-b border-slate-100">
                <th class="px-5 py-3 font-medium"><a href="{{ $sortLink('name') }}" class="hover:text-slate-700">{{ $isCustomer ? __('Customer') : __('Supplier') }}</a></th>
                <th class="px-5 py-3 font-medium text-end"><a href="{{ $sortLink('count') }}" class="hover:text-slate-700">{{ $isCustomer ? __('Invoices') : __('Bills') }}</a></th>
                <th class="px-5 py-3 font-medium text-end"><a href="{{ $sortLink('invoiced') }}" class="hover:text-slate-700">{{ $isCustomer ? __('Invoiced') : __('Billed') }}</a></th>
                <th class="px-5 py-3 font-medium text-end">{{ __('VAT') }}</th>
                <th class="px-5 py-3 font-medium text-end"><a href="{{ $sortLink('received') }}" class="hover:text-slate-700">{{ $isCustomer ? __('Received') : __('Paid') }}</a></th>
                <th class="px-5 py-3 font-medium text-end"><a href="{{ $sortLink('outstanding') }}" class="hover:text-slate-700">{{ __('Outstanding') }}</a></th>
                <th class="px-5 py-3 font-medium">{{ __('Last') }} {{ $isCustomer ? __('invoice') : __('bill') }}</th>
                <th class="px-5 py-3"></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50">
                    <td class="px-5 py-3 font-medium text-slate-800">
                        {{ $row['party']->display_name ?? $row['party']->name }}
                        @if ($row['party']->vat_number)<span class="block text-xs text-slate-400">{{ __('VAT') }} {{ $row['party']->vat_number }}</span>@endif
                    </td>
                    <td class="px-5 py-3 text-end tabular-nums">{{ $row['count'] }}</td>
                    <td class="px-5 py-3 text-end tabular-nums">{{ \App\Support\Money::format($row['invoiced']) }}</td>
                    <td class="px-5 py-3 text-end tabular-nums text-slate-500">{{ \App\Support\Money::format($row['vat']) }}</td>
                    <td class="px-5 py-3 text-end tabular-nums text-emerald-600">{{ \App\Support\Money::format($row['received']) }}</td>
                    <td class="px-5 py-3 text-end tabular-nums font-semibold {{ $row['outstanding'] > 0 ? 'text-amber-600' : 'text-slate-400' }}">{{ \App\Support\Money::format($row['outstanding']) }}</td>
                    <td class="px-5 py-3 text-slate-500">{{ $row['lastDate']?->format('Y-m-d') ?? '—' }}</td>
                    <td class="px-5 py-3 text-end">
                        <a href="{{ route('app.reports.account-statement', [($isCustomer ? 'client_id' : 'supplier_id') => $row['party']->id, 'type' => $isCustomer ? 'customer' : 'supplier']) }}" class="text-xs font-semibold text-brand-700 hover:underline">{{ __('Statement') }}</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="px-5 py-10 text-center text-slate-400">{{ $isCustomer ? __('No customers billed in this period.') : __('No suppliers billed in this period.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
