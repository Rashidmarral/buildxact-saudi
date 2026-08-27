@props(['heading' => 'Trusted By Pharmacies & Healthcare Providers'])

@php
    $clients = \App\Models\ClientLogo::where('is_visible', true)->orderBy('sort_order')->get();
@endphp

@if ($clients->isNotEmpty())
    <section class="border-y border-slate-100 bg-white py-14">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            @if ($heading)
                <p class="reveal text-center text-sm font-semibold uppercase tracking-wide text-slate-400">{{ $heading }}</p>
            @endif
            <div class="reveal-stagger mt-8 flex flex-wrap items-center justify-center gap-x-12 gap-y-8">
                @foreach ($clients as $client)
                    <a href="{{ $client->website_url ?: '#' }}" @if ($client->website_url) target="_blank" rel="noopener" @endif
                       class="group flex h-10 items-center opacity-60 grayscale transition duration-300 hover:opacity-100 hover:grayscale-0 {{ $client->website_url ? '' : 'pointer-events-none' }}">
                        @if ($client->logo_path)
                            <img src="{{ asset('storage/'.$client->logo_path) }}" alt="{{ $client->name }}" loading="lazy" class="h-full w-auto max-w-[140px] object-contain">
                        @else
                            <span class="text-lg font-bold text-slate-400 group-hover:text-teal-800">{{ $client->name }}</span>
                        @endif
                    </a>
                @endforeach
            </div>
        </div>
    </section>
@endif
