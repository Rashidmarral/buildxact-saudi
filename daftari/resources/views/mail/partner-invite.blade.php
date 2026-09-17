@component('mail::message')
# {{ __('Welcome to the partner program') }}

{{ __('Hi :name, your partner application has been approved. Set a password to activate your partner dashboard.', ['name' => $member->name]) }}

@component('mail::button', ['url' => $acceptUrl])
{{ __('Activate your account') }}
@endcomponent
@endcomponent
