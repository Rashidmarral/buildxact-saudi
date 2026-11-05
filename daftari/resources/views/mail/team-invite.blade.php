@component('mail::message')
# {{ __('You\'re invited') }}

{{ __('Hi :name,', ['name' => $member->name]) }}

{{ __(':company has invited you to join their team on :app.', ['company' => $company->name, 'app' => config('app.name')]) }}

@component('mail::button', ['url' => $acceptUrl])
{{ __('Accept invitation') }}
@endcomponent

{{ __('This link expires in :hours hours. If you weren\'t expecting this invite, you can safely ignore this email.', ['hours' => $expiresInHours]) }}

{{ __('Thanks,') }}<br>
{{ $company->name }}
@endcomponent
