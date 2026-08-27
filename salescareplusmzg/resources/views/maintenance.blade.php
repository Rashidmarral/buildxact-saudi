<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>We'll Be Right Back — {{ config('company.name') }}</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 24 24%22 fill=%22%23173c36%22><rect x=%223%22 y=%223%22 width=%2218%22 height=%2218%22 rx=%225%22/><path d=%22M8 12h8M12 8v8%22 stroke=%22%23e35f38%22 stroke-width=%222%22/></svg>">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @if (config('company.theme.primary') || config('company.theme.accent'))
        <style>
            :root {
                @foreach (\App\Support\ColorPalette::ramp(config('company.theme.primary', '#2a9078')) as $shade => $hex)
                    --color-teal-{{ $shade }}: {{ $hex }};
                @endforeach
                @foreach (\App\Support\ColorPalette::ramp(config('company.theme.accent', '#e35f38')) as $shade => $hex)
                    --color-coral-{{ $shade }}: {{ $hex }};
                @endforeach
            }
        </style>
    @endif
</head>
<body class="flex min-h-screen items-center justify-center bg-teal-950 bg-teal-pattern px-4 py-16 text-center">

    <div class="mx-auto max-w-lg">
        @if (config('company.logo_path'))
            <img src="{{ asset('storage/'.config('company.logo_path')) }}" alt="{{ config('company.short_name') }}" class="mx-auto h-12 w-auto object-contain">
        @else
            <span class="text-2xl font-bold text-white">{{ config('company.short_name', config('company.name')) }}</span>
        @endif

        <div class="animate-float mx-auto mt-10 flex h-20 w-20 items-center justify-center rounded-full bg-white/10 ring-1 ring-white/15">
            <svg viewBox="0 0 24 24" class="h-10 w-10 text-coral-400" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>
            </svg>
        </div>

        <h1 class="mt-8 text-3xl font-bold tracking-tight text-white sm:text-4xl">We'll Be Right Back</h1>
        <p class="mt-4 text-lg leading-relaxed text-teal-200">
            {{ $message ?: "We're making some improvements to our website right now. We'll be back online shortly — thanks for your patience." }}
        </p>

        <div class="mt-10 flex flex-wrap items-center justify-center gap-4 text-sm">
            @if (config('company.phone'))
                <a href="tel:{{ config('company.phone') }}" class="inline-flex items-center gap-2 rounded-full border border-white/20 px-5 py-2.5 font-medium text-white transition hover:bg-white/10">
                    <x-icon name="phone" class="h-4 w-4" /> {{ config('company.phone') }}
                </a>
            @endif
            @if (config('company.email'))
                <a href="mailto:{{ config('company.email') }}" class="inline-flex items-center gap-2 rounded-full border border-white/20 px-5 py-2.5 font-medium text-white transition hover:bg-white/10">
                    <x-icon name="mail" class="h-4 w-4" /> {{ config('company.email') }}
                </a>
            @endif
        </div>

        <div class="mt-8 flex items-center justify-center gap-3">
            @if (config('company.social.facebook'))
                <a href="{{ config('company.social.facebook') }}" target="_blank" rel="noopener" class="rounded-full border border-white/20 p-2.5 text-teal-300 transition hover:border-coral-400 hover:text-white" aria-label="Facebook">
                    <svg viewBox="0 0 24 24" class="h-4 w-4" fill="currentColor"><path d="M13 22v-8h2.7l.4-3.3H13V8.6c0-.9.3-1.6 1.7-1.6H16V4.1C15.6 4 14.6 4 13.5 4 11 4 9.3 5.5 9.3 8.3v2.4H6.6V14h2.7v8h3.7Z"/></svg>
                </a>
            @endif
            @if (config('company.social.linkedin'))
                <a href="{{ config('company.social.linkedin') }}" target="_blank" rel="noopener" class="rounded-full border border-white/20 p-2.5 text-teal-300 transition hover:border-coral-400 hover:text-white" aria-label="LinkedIn">
                    <svg viewBox="0 0 24 24" class="h-4 w-4" fill="currentColor"><path d="M4.98 3.5a2 2 0 1 1 0 4 2 2 0 0 1 0-4ZM3 9h4v12H3V9Zm7 0h3.8v1.7h.05c.53-1 1.83-2.05 3.77-2.05 4.03 0 4.78 2.65 4.78 6.1V21h-4v-5.3c0-1.27-.02-2.9-1.77-2.9-1.77 0-2.04 1.38-2.04 2.8V21h-4V9Z"/></svg>
                </a>
            @endif
            @if (config('company.social.twitter'))
                <a href="{{ config('company.social.twitter') }}" target="_blank" rel="noopener" class="rounded-full border border-white/20 p-2.5 text-teal-300 transition hover:border-coral-400 hover:text-white" aria-label="Twitter">
                    <svg viewBox="0 0 24 24" class="h-4 w-4" fill="currentColor"><path d="M22 5.9c-.7.3-1.5.6-2.3.7.8-.5 1.5-1.3 1.8-2.3-.8.5-1.7.8-2.6 1a4.1 4.1 0 0 0-7 3.7A11.6 11.6 0 0 1 3.4 4.6a4.1 4.1 0 0 0 1.3 5.5c-.7 0-1.3-.2-1.9-.5v.1c0 2 1.4 3.6 3.3 4a4.2 4.2 0 0 1-1.9.1 4.1 4.1 0 0 0 3.8 2.9A8.3 8.3 0 0 1 2 18.4a11.6 11.6 0 0 0 6.3 1.9c7.5 0 11.7-6.3 11.7-11.7v-.5c.8-.6 1.5-1.3 2-2.2Z"/></svg>
                </a>
            @endif
            @if (config('company.social.instagram'))
                <a href="{{ config('company.social.instagram') }}" target="_blank" rel="noopener" class="rounded-full border border-white/20 p-2.5 text-teal-300 transition hover:border-coral-400 hover:text-white" aria-label="Instagram">
                    <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.75"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1"/></svg>
                </a>
            @endif
        </div>

        <p class="mt-10 text-xs uppercase tracking-wide text-teal-400">{{ config('company.name') }}</p>
    </div>

</body>
</html>
