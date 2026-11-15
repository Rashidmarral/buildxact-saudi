{{--
    Hamly-parity two-sided Balance Sheet table, shared by -detailed
    (account-level rows under each category) and -summary (category
    totals only). Expects: $assetCategories, $equityCategories,
    $liabilityCategories, $totalAssets, $totalLiabilities, $totalEquity,
    $detailed (bool).
--}}
<div class="bg-white rounded-xl border border-slate-100 overflow-x-auto shadow-sm">
    <div class="grid md:grid-cols-2 divide-y md:divide-y-0 md:divide-x divide-slate-100">
        <table class="w-full text-sm align-top">
            <thead>
                <tr class="text-left text-slate-500 bg-slate-50 border-b-2 border-slate-200">
                    @if ($detailed)<th class="px-5 py-3 font-semibold">{{ __('Ledger no.') }}</th>@endif
                    <th class="px-5 py-3 font-semibold" @if (! $detailed) colspan="2" @endif>{{ __('Assets') }}</th>
                    <th class="px-5 py-3 font-semibold text-end">{{ __('Amount') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($assetCategories as $categoryLabel => $category)
                    @include('user.reports.partials.balance-sheet-category-rows', ['categoryLabel' => $categoryLabel, 'category' => $category, 'detailed' => $detailed])
                @empty
                    <tr><td colspan="3" class="px-5 py-10 text-center text-slate-400">{{ __('No activity yet.') }}</td></tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr class="bg-slate-900 font-bold text-white">
                    <td colspan="2" class="px-5 py-3.5">{{ __('Total') }}</td>
                    <td class="px-5 py-3.5 text-end tabular-nums">{{ number_format($totalAssets, 2) }}</td>
                </tr>
            </tfoot>
        </table>

        <table class="w-full text-sm align-top">
            <thead>
                <tr class="text-left text-slate-500 bg-slate-50 border-b-2 border-slate-200">
                    @if ($detailed)<th class="px-5 py-3 font-semibold">{{ __('Ledger no.') }}</th>@endif
                    <th class="px-5 py-3 font-semibold" @if (! $detailed) colspan="2" @endif>{{ __('Liabilities') }}</th>
                    <th class="px-5 py-3 font-semibold text-end">{{ __('Amount') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr class="bg-slate-100">
                    <td colspan="3" class="px-5 py-2 text-xs font-bold uppercase tracking-wide text-slate-700">{{ __('Capital') }}</td>
                </tr>
                @forelse ($equityCategories as $categoryLabel => $category)
                    @include('user.reports.partials.balance-sheet-category-rows', ['categoryLabel' => $categoryLabel, 'category' => $category, 'detailed' => $detailed])
                @empty
                    <tr><td colspan="3" class="px-5 py-4 text-center text-slate-400">{{ __('No activity yet.') }}</td></tr>
                @endforelse

                <tr class="bg-slate-100">
                    <td colspan="3" class="px-5 py-2 text-xs font-bold uppercase tracking-wide text-slate-700">{{ __('Liabilities') }}</td>
                </tr>
                @forelse ($liabilityCategories as $categoryLabel => $category)
                    @include('user.reports.partials.balance-sheet-category-rows', ['categoryLabel' => $categoryLabel, 'category' => $category, 'detailed' => $detailed])
                @empty
                    <tr><td colspan="3" class="px-5 py-4 text-center text-slate-400">{{ __('No liabilities.') }}</td></tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr class="bg-slate-900 font-bold text-white">
                    <td colspan="2" class="px-5 py-3.5">{{ __('Total') }}</td>
                    <td class="px-5 py-3.5 text-end tabular-nums">{{ number_format($totalLiabilities + $totalEquity, 2) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
