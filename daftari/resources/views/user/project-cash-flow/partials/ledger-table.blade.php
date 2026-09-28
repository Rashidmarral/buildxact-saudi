{{-- Shared by show.blade.php (project statement) and bank-account-show.blade.php (account statement). Expects $ledger = ['rows' => Collection, 'opening_balance' => float, 'closing_balance' => float]. --}}
<div class="bg-white rounded-xl border border-slate-100 overflow-hidden">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-slate-500 border-b border-slate-100">
                <th class="px-6 py-3 font-medium">{{ __('Date') }}</th>
                <th class="px-6 py-3 font-medium">{{ __('Type') }}</th>
                <th class="px-6 py-3 font-medium">{{ __('Number') }}</th>
                <th class="px-6 py-3 font-medium">{{ __('Party') }}</th>
                <th class="px-6 py-3 font-medium text-right">{{ __('In') }}</th>
                <th class="px-6 py-3 font-medium text-right">{{ __('Out') }}</th>
                <th class="px-6 py-3 font-medium text-right">{{ __('Running balance') }}</th>
            </tr>
        </thead>
        <tbody>
            <tr class="border-b border-slate-50 bg-slate-50/60">
                <td colspan="6" class="px-6 py-2.5 font-medium text-slate-500">{{ __('Opening balance') }}</td>
                <td class="px-6 py-2.5 text-right font-semibold tabular-nums">{{ \App\Support\Money::format($ledger['opening_balance']) }}</td>
            </tr>
            @forelse ($ledger['rows'] as $row)
                <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50 cursor-pointer" onclick="window.location='{{ $row['url'] }}'">
                    <td class="px-6 py-3">{{ \App\Support\PlatformFormat::date($row['date']) }}</td>
                    <td class="px-6 py-3">
                        @if ($row['type'] === 'receipt')
                            <span class="inline-block rounded-full bg-emerald-50 text-emerald-700 text-xs font-medium px-2.5 py-1">{{ __('Receipt') }}</span>
                        @elseif ($row['type'] === 'payment')
                            <span class="inline-block rounded-full bg-amber-50 text-amber-700 text-xs font-medium px-2.5 py-1">{{ __('Payment') }}</span>
                        @elseif ($row['type'] === 'withdrawal')
                            <span class="inline-block rounded-full bg-orange-50 text-orange-700 text-xs font-medium px-2.5 py-1">{{ __('Withdrawal') }}</span>
                        @elseif ($row['type'] === 'deposit')
                            <span class="inline-block rounded-full bg-sky-50 text-sky-700 text-xs font-medium px-2.5 py-1">{{ __('Deposit') }}</span>
                        @else
                            <span class="inline-block rounded-full bg-slate-100 text-slate-600 text-xs font-medium px-2.5 py-1">{{ __('Transfer') }}</span>
                        @endif
                    </td>
                    <td class="px-6 py-3 font-medium text-brand-700">{{ $row['number'] ?: '—' }}</td>
                    <td class="px-6 py-3">{{ $row['party'] }}</td>
                    <td class="px-6 py-3 text-right tabular-nums {{ $row['in_amount'] > 0 ? 'text-emerald-600' : 'text-slate-300' }}">{{ $row['in_amount'] > 0 ? \App\Support\Money::format($row['in_amount']) : '—' }}</td>
                    <td class="px-6 py-3 text-right tabular-nums {{ $row['out_amount'] > 0 ? 'text-red-600' : 'text-slate-300' }}">{{ $row['out_amount'] > 0 ? \App\Support\Money::format($row['out_amount']) : '—' }}</td>
                    <td class="px-6 py-3 text-right tabular-nums font-semibold text-slate-900">{{ \App\Support\Money::format($row['balance_after']) }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-6 py-12 text-center text-sm text-slate-400">{{ __('No cash movement recorded yet.') }}</td></tr>
            @endforelse
            <tr class="border-t-2 border-slate-200 bg-slate-50/60">
                <td colspan="6" class="px-6 py-2.5 font-medium text-slate-500">{{ __('Closing balance') }}</td>
                <td class="px-6 py-2.5 text-right font-semibold tabular-nums">{{ \App\Support\Money::format($ledger['closing_balance']) }}</td>
            </tr>
        </tbody>
    </table>
</div>
