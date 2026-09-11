{{-- Runtime white-labeling: favicon + brand color overrides from Platform
     Settings → Branding. Included in every layout <head> so a super admin's
     color/favicon choice applies everywhere without a rebuild. Uses the
     same "override the exact compiled utility-class selector" technique as
     dark mode — literal CSS, never @apply, and never a single combined
     selector list mixing unrelated pseudo-elements (that silently drops
     the whole rule in some builds). Renders nothing when nothing is
     configured, so a fresh install looks exactly as it did before this
     setting existed. --}}
@php($__branding ??= \App\Support\PlatformBranding::all())
@if ($__branding['favicon_path'])
    <link rel="icon" href="{{ Storage::url($__branding['favicon_path']) }}">
@endif
@if ($__branding['primary_color'] || $__branding['secondary_color'] || $__branding['sidebar_color'])
    <style>
        @if ($__branding['primary_color'])
            .bg-brand-600, .bg-brand-700, .hover\:bg-brand-700:hover, .hover\:bg-brand-900:hover, .bg-brand-800, .bg-brand-900 { background-color: {{ $__branding['primary_color'] }} !important; }
            .text-brand-600, .text-brand-700, .text-brand-800, .hover\:text-brand-700:hover { color: {{ $__branding['primary_color'] }} !important; }
            .border-brand-500, .focus\:border-brand-500:focus, .ring-brand-500 { border-color: {{ $__branding['primary_color'] }} !important; }
            .to-brand-600, .to-brand-700 { --tw-gradient-to: {{ $__branding['primary_color'] }} var(--tw-gradient-to-position) !important; }
        @endif
        @if ($__branding['secondary_color'])
            .from-brand-400, .from-brand-500 { --tw-gradient-from: {{ $__branding['secondary_color'] }} var(--tw-gradient-from-position) !important; }
        @endif
        @if ($__branding['sidebar_color'])
            aside.bg-gradient-to-b.from-slate-950 { background: {{ $__branding['sidebar_color'] }} !important; }
        @endif
    </style>
@endif
