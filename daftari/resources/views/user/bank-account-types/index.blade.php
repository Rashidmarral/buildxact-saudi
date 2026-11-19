@extends('layouts.app')

@section('title', __('Account Types'))

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <div>
        <h2 class="text-lg font-semibold text-slate-900">{{ __('Account Types') }}</h2>
        <p class="text-sm text-slate-500 mt-1">{{ __('Add your own labels for Cash & Bank accounts — e.g. "Digital Wallet" or "POS Till" — beyond the built-in Cash and Bank.') }}</p>
    </div>
    <a href="{{ route('app.bank-account-types.create') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">+ {{ __('Add type') }}</a>
</div>

<div class="bg-white rounded-xl border border-slate-100 p-6 mb-6">
    <h4 class="font-semibold text-slate-900 mb-3">{{ __('Built-in types') }}</h4>
    <div class="flex flex-wrap gap-2">
        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600">{{ __('Bank') }}</span>
        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600">{{ __('Cash') }}</span>
    </div>
    <p class="text-xs text-slate-400 mt-3">{{ __('Always available when creating an account, and can\'t be renamed or removed here.') }}</p>
</div>

<div class="bg-white rounded-xl border border-slate-100">
    @if ($types->isEmpty())
        <p class="px-6 py-8 text-sm text-slate-500">{{ __('No custom account types yet — add one above.') }}</p>
    @else
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="px-6 py-3 font-medium">{{ __('Type name') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Behaves like') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Accounts using it') }}</th>
                    <th class="px-6 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($types as $type)
                    <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50">
                        <td class="px-6 py-3 font-medium text-slate-800">{{ $type->name }}</td>
                        <td class="px-6 py-3 text-slate-500">{{ $type->nature === 'cash' ? __('Cash') : __('Bank') }}</td>
                        <td class="px-6 py-3 text-slate-500">{{ $type->bank_accounts_count }}</td>
                        <td class="px-6 py-3 text-right">
                            <x-row-actions>
                                <x-icon-button menu href="{{ route('app.bank-account-types.edit', $type) }}" icon="pencil" title="{{ __('Edit') }}" />
                                <form method="POST" action="{{ route('app.bank-account-types.destroy', $type) }}" onsubmit="return confirm('{{ __('Delete this account type?') }}')">
                                    @csrf @method('DELETE')
                                    <x-icon-button menu type="submit" icon="trash" color="red" title="{{ __('Delete') }}" />
                                </form>
                            </x-row-actions>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
@endsection
