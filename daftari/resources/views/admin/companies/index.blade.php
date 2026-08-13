@extends('layouts.admin')

@section('title', __('Companies'))

@section('content')
<form method="GET" class="mb-6">
    <input type="text" name="q" value="{{ request('q') }}" placeholder="{{ __('Search companies...') }}" class="w-full max-w-sm rounded-lg border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
</form>

<div class="bg-white rounded-xl border border-slate-100">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-slate-500 border-b border-slate-100">
                <th class="px-6 py-3 font-medium">{{ __('Company') }}</th>
                <th class="px-6 py-3 font-medium">{{ __('Users') }}</th>
                <th class="px-6 py-3 font-medium">{{ __('Invoices') }}</th>
                <th class="px-6 py-3 font-medium">{{ __('Status') }}</th>
                <th class="px-6 py-3"></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($companies as $company)
                <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50">
                    <td class="px-6 py-3"><a href="{{ route('admin.companies.show', $company) }}" class="font-medium text-brand-700 hover:underline">{{ $company->name }}</a></td>
                    <td class="px-6 py-3">{{ $company->users_count }}</td>
                    <td class="px-6 py-3">{{ $company->invoices_count }}</td>
                    <td class="px-6 py-3">
                        @if ($company->status === 'active')
                            <span class="text-brand-700">{{ __('Active') }}</span>
                        @else
                            <span class="text-red-600">{{ __('Suspended') }}</span>
                        @endif
                    </td>
                    <td class="px-6 py-3 text-right">
                        @if ($company->status === 'active')
                            <form method="POST" action="{{ route('admin.companies.suspend', $company) }}" onsubmit="return confirm('{{ __('Suspend this company?') }}')">
                                @csrf
                                <button type="submit" class="text-red-600 hover:underline">{{ __('Suspend') }}</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('admin.companies.activate', $company) }}">
                                @csrf
                                <button type="submit" class="text-brand-700 hover:underline">{{ __('Activate') }}</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $companies->links() }}</div>
@endsection
