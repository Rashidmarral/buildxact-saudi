@component('mail::message')
# {{ __('Sign in to your account') }}

{{ __('Dear :name,', ['name' => $client->name]) }}

{{ __('Use the button below to view your invoices and account statement with :company. This link expires in 15 minutes and can only be used once.', ['company' => $company->name]) }}

@component('mail::button', ['url' => $loginUrl])
{{ __('Sign in') }}
@endcomponent

{{ __("If you didn't request this, you can safely ignore this email.") }}
@endcomponent
