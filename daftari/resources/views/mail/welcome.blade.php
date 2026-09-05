@component('mail::message')
# {{ __('Welcome to :app', ['app' => config('app.name')]) }}

{{ __('Hi :name,', ['name' => $company->name]) }}

{{ __('Your account is ready. Here\'s what to do first:') }}

- {{ __('Add your company details and VAT number in Settings') }}
- {{ __('Create your first client') }}
- {{ __('Send your first invoice') }}

@if ($trialEndsAt)
{{ __('Your free trial runs until :date.', ['date' => $trialEndsAt->format('Y-m-d')]) }}
@endif

@component('mail::button', ['url' => $dashboardUrl])
{{ __('Go to your dashboard') }}
@endcomponent

{{ __('Thanks,') }}<br>
{{ config('app.name') }}
@endcomponent
