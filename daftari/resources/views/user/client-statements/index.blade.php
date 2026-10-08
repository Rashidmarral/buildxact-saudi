@extends('layouts.app')

@section('title', __('Client Statements'))

@section('content')
<div class="mb-6">
    <h2 class="text-lg font-semibold text-slate-900">{{ __('Client Statements') }}</h2>
    <p class="text-sm text-slate-500 mt-1">{{ __('Pick a client to see every invoice, payment, and credit note on their account — a running balance you can hand them as a branded, bilingual PDF.') }}</p>
</div>

<div class="bg-white rounded-xl border border-slate-100 p-6">
    @if ($clients->isEmpty())
        <p class="text-sm text-slate-400 py-6 text-center">{{ __('No clients yet.') }}</p>
    @else
        <div class="divide-y divide-slate-50">
            @foreach ($clients as $client)
                <a href="{{ route('app.client-statements.show', $client) }}" class="flex items-center justify-between py-3 hover:bg-slate-50 -mx-2 px-2 rounded-lg">
                    <span>
                        <span class="font-medium text-slate-700">{{ $client->display_name }}</span>
                        <span class="text-xs text-slate-400 ms-2">{{ $client->client_code }}</span>
                    </span>
                    <span class="text-slate-300">→</span>
                </a>
            @endforeach
        </div>
    @endif
</div>
@endsection
