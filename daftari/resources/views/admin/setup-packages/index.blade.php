@extends('layouts.admin')

@section('title', __('Setup Packages'))

@section('content')
<div class="flex items-center justify-between mb-6">
    <p class="text-sm text-slate-500">{{ __('Done-for-you setup packages shown on the public /setup-packages page. Leave price blank for "contact us for pricing".') }}</p>
    <a href="{{ route('admin.setup-packages.create') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('+ New setup package') }}</a>
</div>

<div class="bg-white rounded-xl border border-slate-100 overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-slate-500 border-b border-slate-100">
                <th class="px-6 py-3 font-medium">{{ __('Name') }}</th>
                <th class="px-6 py-3 font-medium">{{ __('Price') }}</th>
                <th class="px-6 py-3 font-medium">{{ __('Requests') }}</th>
                <th class="px-6 py-3 font-medium">{{ __('Status') }}</th>
                <th class="px-6 py-3"></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($setupPackages as $package)
                <tr class="border-b border-slate-50 last:border-0">
                    <td class="px-6 py-3 font-medium text-slate-800">{{ $package->name() }}</td>
                    <td class="px-6 py-3 text-slate-600">{{ $package->priceLabel() }}</td>
                    <td class="px-6 py-3 text-slate-500">{{ $package->requests()->count() }}</td>
                    <td class="px-6 py-3">
                        @if ($package->is_active)
                            <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">{{ __('Active') }}</span>
                        @else
                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-500">{{ __('Inactive') }}</span>
                        @endif
                    </td>
                    <td class="px-6 py-3 text-right space-x-3 rtl:space-x-reverse">
                        <a href="{{ route('admin.setup-packages.edit', $package) }}" class="text-brand-700 hover:underline">{{ __('Edit') }}</a>
                        <form method="POST" action="{{ route('admin.setup-packages.destroy', $package) }}" class="inline" onsubmit="return confirm('{{ __('Delete this setup package?') }}')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-red-600 hover:underline">{{ __('Delete') }}</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-6 py-8 text-center text-slate-400">{{ __('No setup packages defined yet.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
