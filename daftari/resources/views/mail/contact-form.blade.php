@component('mail::message')
# {{ __('New contact form message') }}

**{{ __('From') }}:** {{ $fromName }} ({{ $fromEmail }})

{{ $messageBody }}

{{ __('Reply directly to this email to respond to :name.', ['name' => $fromName]) }}
@endcomponent
