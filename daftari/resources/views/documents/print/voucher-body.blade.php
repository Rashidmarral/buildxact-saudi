{{--
    Shared printable body for Receipt and Payment Vouchers, used by both
    show.blade.php (browser view/print) and the standalone PDF wrapper —
    keeps the two renders identical. $type is 'receipt' or 'payment'.
--}}
@php($isReceipt = $type === 'receipt')
<div class="flex justify-between items-start border-b border-slate-100 pb-4 mt-4">
    <h2 class="text-xl font-bold text-slate-900">{{ $isReceipt ? __('Receipt Voucher') : __('Payment Voucher') }}</h2>
    <div class="text-end text-xs text-slate-500">
        <div>{{ __('No.') }}: {{ $voucher->voucher_number }}</div>
        <div>{{ __('Date') }}: {{ \App\Support\PlatformFormat::date($voucher->date) }}</div>
    </div>
</div>
<div class="mt-6 space-y-4 text-sm">
    <div><span class="text-slate-400">{{ $isReceipt ? __('Received from:') : __('Paid to:') }}</span> <span class="font-medium text-slate-800">{{ $isReceipt ? $voucher->payer_name : $voucher->payee_name }}</span> @if ($voucher->party_type !== 'manual')<span class="ms-1 text-xs text-slate-400">({{ ucfirst($voucher->party_type) }})</span>@endif</div>
    @if ($voucher->party_vat_number)<div><span class="text-slate-400">{{ __('VAT number:') }}</span> <span class="font-medium text-slate-800">{{ $voucher->party_vat_number }}</span></div>@endif
    <div><span class="text-slate-400">{{ __('Amount:') }}</span> <span class="font-bold text-slate-900">SAR {{ number_format($voucher->amount, 2) }}</span></div>
    <div><span class="text-slate-400">{{ __('Account:') }}</span> <span class="font-medium text-slate-800">{{ $voucher->bankAccount->name }}</span></div>
    @if ($voucher->counterAccount)<div><span class="text-slate-400">{{ __('Counter account:') }}</span> <span class="font-medium text-slate-800">{{ $voucher->counterAccount->label() }}</span></div>@endif
    <div><span class="text-slate-400">{{ __('Payment method:') }}</span> <span class="font-medium text-slate-800">{{ ucfirst(str_replace('_', ' ', $voucher->method)) }}</span></div>
    @if ($isReceipt && $voucher->invoice)
        <div><span class="text-slate-400">{{ __('Applied to invoice:') }}</span> <a href="{{ route('app.invoices.show', $voucher->invoice) }}" class="font-medium text-brand-700 hover:underline">{{ $voucher->invoice->invoice_number }}</a></div>
    @endif
    @if (! $isReceipt && $voucher->bill)
        <div><span class="text-slate-400">{{ __('Applied to bill:') }}</span> <a href="{{ route('app.bills.show', $voucher->bill) }}" class="font-medium text-brand-700 hover:underline">{{ $voucher->bill->bill_number }}</a></div>
    @endif
    @if (! $isReceipt && $voucher->expense)
        <div><span class="text-slate-400">{{ __('Related expense:') }}</span> <span class="font-medium text-slate-800">{{ $voucher->expense->vendor_name }} — {{ $voucher->expense->description }}</span></div>
    @endif
    @if ($voucher->reference)
        <div><span class="text-slate-400">{{ __('Reference:') }}</span> <span class="font-medium text-slate-800">{{ $voucher->reference }}</span></div>
    @endif
    @if ($voucher->notes)
        <div><span class="text-slate-400">{{ __('Notes:') }}</span> <span class="font-medium text-slate-800">{{ $voucher->notes }}</span></div>
    @endif
</div>
<div class="mt-10 pt-6 border-t border-slate-100 grid grid-cols-2 text-xs text-slate-400 text-center">
    <div>{{ $isReceipt ? __('Received by') : __('Paid by') }}</div>
    <div>{{ __('Authorized signature') }}</div>
</div>
