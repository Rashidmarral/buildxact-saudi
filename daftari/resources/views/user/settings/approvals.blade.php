@extends('layouts.app')

@section('title', __('Approvals'))

@section('content')
<div class="max-w-2xl space-y-6">
    <div>
        <h1 class="text-lg font-semibold text-slate-900">{{ __('Approvals') }}</h1>
        <p class="text-sm text-slate-500 mt-1">{{ __('Require a designated approver to sign off on documents above a set amount, before they can be posted or sent. Leave a threshold empty to keep instant approval for that document type.') }}</p>
    </div>

    <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
        {{ __('Only users whose role includes the "Approve purchase orders & expenses" permission can approve or reject items above these thresholds — set that up under Members & Roles.') }}
    </div>

    <form method="POST" action="{{ route('app.settings.approvals.update') }}" class="bg-white rounded-xl border border-slate-100 p-6 space-y-4">
        @csrf
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Purchase order approval threshold (SAR)') }}</label>
            <input type="number" step="0.01" min="0" name="po_approval_threshold" value="{{ old('po_approval_threshold', $company->po_approval_threshold) }}" placeholder="{{ __('No threshold — instant approval') }}" class="w-full rounded-lg border border-slate-200 text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Expense approval threshold (SAR)') }}</label>
            <input type="number" step="0.01" min="0" name="expense_approval_threshold" value="{{ old('expense_approval_threshold', $company->expense_approval_threshold) }}" placeholder="{{ __('No threshold — instant approval') }}" class="w-full rounded-lg border border-slate-200 text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Invoice approval threshold (SAR)') }}</label>
            <input type="number" step="0.01" min="0" name="invoice_approval_threshold" value="{{ old('invoice_approval_threshold', $company->invoice_approval_threshold) }}" placeholder="{{ __('No threshold — instant send') }}" class="w-full rounded-lg border border-slate-200 text-sm">
            <p class="text-xs text-slate-400 mt-1">{{ __('An invoice at or above this total is parked pending approval instead of being sent and posted to the ledger.') }}</p>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Quotation approval threshold (SAR)') }}</label>
            <input type="number" step="0.01" min="0" name="quotation_approval_threshold" value="{{ old('quotation_approval_threshold', $company->quotation_approval_threshold) }}" placeholder="{{ __('No threshold — instant issue') }}" class="w-full rounded-lg border border-slate-200 text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Manual journal entry approval threshold (SAR)') }}</label>
            <input type="number" step="0.01" min="0" name="manual_journal_approval_threshold" value="{{ old('manual_journal_approval_threshold', $company->manual_journal_approval_threshold) }}" placeholder="{{ __('No threshold — instant post') }}" class="w-full rounded-lg border border-slate-200 text-sm">
            <p class="text-xs text-slate-400 mt-1">{{ __('A manual journal entry at or above this total waits in Journals → Pending approvals instead of posting straight to the ledger.') }}</p>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Payment voucher approval threshold (SAR)') }}</label>
            <input type="number" step="0.01" min="0" name="payment_voucher_approval_threshold" value="{{ old('payment_voucher_approval_threshold', $company->payment_voucher_approval_threshold) }}" placeholder="{{ __('No threshold — instant post') }}" class="w-full rounded-lg border border-slate-200 text-sm">
            <p class="text-xs text-slate-400 mt-1">{{ __('Cash paid out at or above this amount waits for approval before any bill payment is recorded or the bank/cash account is actually debited.') }}</p>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Receipt voucher approval threshold (SAR)') }}</label>
            <input type="number" step="0.01" min="0" name="receipt_voucher_approval_threshold" value="{{ old('receipt_voucher_approval_threshold', $company->receipt_voucher_approval_threshold) }}" placeholder="{{ __('No threshold — instant post') }}" class="w-full rounded-lg border border-slate-200 text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Bill approval threshold (SAR)') }}</label>
            <input type="number" step="0.01" min="0" name="bill_approval_threshold" value="{{ old('bill_approval_threshold', $company->bill_approval_threshold) }}" placeholder="{{ __('No threshold — instant post') }}" class="w-full rounded-lg border border-slate-200 text-sm">
            <p class="text-xs text-slate-400 mt-1">{{ __('A bill at or above this total can still be saved as a draft, but posting it (immediately or via the Post button) routes it to approval instead.') }}</p>
        </div>
        <button type="submit" class="rounded-lg bg-brand-600 px-5 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Save') }}</button>
    </form>

    <div>
        <h2 class="text-lg font-semibold text-slate-900">{{ __('Multi-tier approval chains') }}</h2>
        <p class="text-sm text-slate-500 mt-1">{{ __('Optionally require a sequence of roles to sign off in order — e.g. a purchase order over 5,000 SAR needs the Manager role, then Finance; over 20,000 SAR it also needs the GM role afterward. Adding a step for a document type replaces its flat threshold above with this chain.') }}</p>
    </div>

    <div class="bg-white rounded-xl border border-slate-100 p-6 space-y-5">
        @foreach (['purchase_order' => __('Purchase orders'), 'expense' => __('Expenses'), 'invoice' => __('Invoices (send, cancel & credit note void)')] as $type => $label)
            <div>
                <h3 class="text-sm font-semibold text-slate-800">{{ $label }}</h3>
                <div class="mt-2 space-y-2">
                    @forelse ($chainSteps->get($type, collect()) as $step)
                        <div class="flex items-center justify-between rounded-lg border border-slate-100 bg-slate-50 px-3 py-2 text-sm">
                            <span class="font-medium text-slate-700">{{ __('Step :n', ['n' => $step->step_number]) }} — {{ $step->role?->name }}</span>
                            <span class="text-slate-500">{{ __('≥ :amount SAR', ['amount' => number_format($step->min_amount, 2)]) }}</span>
                            <form method="POST" action="{{ route('app.settings.approvals.chain-steps.destroy', $step) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs font-semibold text-red-600 hover:underline">{{ __('Remove') }}</button>
                            </form>
                        </div>
                    @empty
                        <p class="text-xs text-slate-400">{{ __('No chain configured — the flat threshold above applies.') }}</p>
                    @endforelse
                </div>
                <form method="POST" action="{{ route('app.settings.approvals.chain-steps.store') }}" class="mt-3 flex flex-wrap items-end gap-3">
                    @csrf
                    <input type="hidden" name="document_type" value="{{ $type }}">
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Approver role') }}</label>
                        <select name="role_id" required class="rounded-lg border border-slate-200 text-sm">
                            <option value="">{{ __('Select a role') }}</option>
                            @foreach ($roles as $role)
                                <option value="{{ $role->id }}">{{ $role->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Required at or above (SAR)') }}</label>
                        <input type="number" step="0.01" min="0" name="min_amount" required class="w-40 rounded-lg border border-slate-200 text-sm">
                    </div>
                    <button type="submit" class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-900">{{ __('Add step') }}</button>
                </form>
            </div>
        @endforeach
    </div>

    <div>
        <h2 class="text-lg font-semibold text-slate-900">{{ __('Overdue invoice reminders') }}</h2>
        <p class="text-sm text-slate-500 mt-1">{{ __('Automatically email clients an escalating reminder — friendly at 7 days overdue, firmer at 14, a final notice at 30 — for as long as an invoice stays unpaid.') }}</p>
    </div>

    <form method="POST" action="{{ route('app.settings.approvals.dunning') }}" class="bg-white rounded-xl border border-slate-100 p-6">
        @csrf
        <label class="flex items-center gap-3">
            <input type="checkbox" name="invoice_dunning_enabled" value="1" @checked($company->invoice_dunning_enabled) class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
            <span class="text-sm font-medium text-slate-700">{{ __('Send automated overdue-invoice reminders') }}</span>
        </label>
        <button type="submit" class="mt-4 rounded-lg bg-brand-600 px-5 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Save') }}</button>
    </form>

    <div>
        <h2 class="text-lg font-semibold text-slate-900">{{ __('Accounting period lock') }}</h2>
        <p class="text-sm text-slate-500 mt-1">{{ __('Once a period is closed and reported, prevent any invoice, bill, expense, payment, or journal entry from being posted with a date on or before the lock date — across every module.') }}</p>
    </div>

    @if ($company->accounting_lock_date)
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            {{ __('The books are currently locked through :date.', ['date' => $company->accounting_lock_date->format('Y-m-d')]) }}
        </div>
    @endif

    <div class="bg-white rounded-xl border border-slate-100 p-6 space-y-4">
        <h3 class="text-sm font-semibold text-slate-800">{{ __('Close a period') }}</h3>
        <form method="POST" action="{{ route('app.settings.approvals.close-period') }}" class="flex items-end gap-3">
            @csrf
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Lock through date') }}</label>
                <input type="date" name="accounting_lock_date" required value="{{ old('accounting_lock_date') }}" class="rounded-lg border border-slate-200 text-sm">
            </div>
            <button type="submit" class="rounded-lg bg-brand-600 px-5 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Close') }}</button>
        </form>
        <p class="text-xs text-slate-400">{{ __('Can only move forward — once reported, a period stays closed until explicitly reopened below, with a reason.') }}</p>
    </div>

    <div class="bg-white rounded-xl border border-slate-100 p-6 space-y-4">
        <h3 class="text-sm font-semibold text-slate-800">{{ __('Reopen the books') }}</h3>
        <form method="POST" action="{{ route('app.settings.approvals.reopen-period') }}" class="space-y-3">
            @csrf
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('New lock date (blank = fully open)') }}</label>
                <input type="date" name="accounting_lock_date" value="{{ old('accounting_lock_date') }}" class="w-full rounded-lg border border-slate-200 text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Reason (required)') }}</label>
                <textarea name="reason" rows="2" required class="w-full rounded-lg border border-slate-200 text-sm">{{ old('reason') }}</textarea>
            </div>
            <button type="submit" class="rounded-lg border border-red-200 text-red-600 px-5 py-2 text-sm font-semibold hover:bg-red-50">{{ __('Reopen') }}</button>
        </form>
    </div>

    @if ($periodClosures->isNotEmpty())
        <div class="bg-white rounded-xl border border-slate-100 overflow-hidden">
            <div class="px-5 py-3 border-b border-slate-100">
                <h3 class="text-sm font-semibold text-slate-900">{{ __('Closure history') }}</h3>
            </div>
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-slate-500 border-b border-slate-100">
                        <th class="px-5 py-2 font-medium">{{ __('Date') }}</th>
                        <th class="px-5 py-2 font-medium">{{ __('Action') }}</th>
                        <th class="px-5 py-2 font-medium">{{ __('Lock date') }}</th>
                        <th class="px-5 py-2 font-medium">{{ __('By') }}</th>
                        <th class="px-5 py-2 font-medium">{{ __('Reason') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($periodClosures as $closure)
                        <tr class="border-b border-slate-50 last:border-0">
                            <td class="px-5 py-2 text-slate-500">{{ $closure->created_at->format('Y-m-d H:i') }}</td>
                            <td class="px-5 py-2">
                                <span class="inline-block rounded-full px-2 py-0.5 text-xs font-medium {{ $closure->action === 'close' ? 'bg-amber-50 text-amber-700' : 'bg-red-50 text-red-600' }}">
                                    {{ $closure->action === 'close' ? __('Closed') : __('Reopened') }}
                                </span>
                            </td>
                            <td class="px-5 py-2 text-slate-600">{{ $closure->lock_date?->format('Y-m-d') ?? __('— fully open') }}</td>
                            <td class="px-5 py-2 text-slate-600">{{ $closure->performer?->name ?? __('Unknown') }}</td>
                            <td class="px-5 py-2 text-slate-500">{{ $closure->reason ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
