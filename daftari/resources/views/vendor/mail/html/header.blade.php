@props(['url'])
@php
    // Every markdown mail (welcome, receipts, reminders, invites...)
    // renders through this one shared partial, so swapping in the
    // configured logo here — falling back to the platform logo, then to
    // the plain app-name text exactly as before this setting existed —
    // applies it everywhere at once instead of touching each mailable.
    $__branding = \App\Support\PlatformBranding::all();
    $__logo = $__branding['email_logo_path'] ?: $__branding['logo_path'];
@endphp
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
@if ($__logo)
<img src="{{ \Illuminate\Support\Facades\Storage::url($__logo) }}" class="logo" alt="{{ trim($slot) }}">
@elseif (trim($slot) === 'Laravel')
<img src="https://laravel.com/img/notification-logo-v2.1.png" class="logo" alt="Laravel Logo">
@else
{!! $slot !!}
@endif
</a>
</td>
</tr>
