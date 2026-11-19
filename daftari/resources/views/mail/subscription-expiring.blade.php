@component('mail::message')
@if ($subscription->isTrial())
# {{ __('Your trial is ending soon') }}

{{ __('Hi :name,', ['name' => $company->name]) }}

{{ __('Your free trial ends on :date (:days day(s) left). Choose a plan to keep using :app without interruption.', ['date' => $subscription->current_period_end->format('Y-m-d'), 'days' => $daysLeft, 'app' => config('app.name')]) }}
@else
# {{ __('Your subscription is renewing soon') }}

{{ __('Hi :name,', ['name' => $company->name]) }}

{{ __('Your :plan plan is set to renew on :date (:days day(s) left). Payments aren\'t collected automatically yet, so please renew manually to avoid any interruption.', ['plan' => $plan->name, 'date' => $subscription->current_period_end->format('Y-m-d'), 'days' => $daysLeft]) }}
@endif

@component('mail::button', ['url' => $billingUrl])
{{ __('Manage billing') }}
@endcomponent

{{ __('Thanks,') }}<br>
{{ config('app.name') }}
@endcomponent
