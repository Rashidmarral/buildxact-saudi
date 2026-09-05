@component('mail::message')
# {{ __('Payment received') }}

{{ __('Hi :name,', ['name' => $company->name]) }}

{{ __('Thanks for your payment. Here are the details:') }}

@component('mail::table')
| | |
|:---|---:|
| {{ __('Plan') }} | {{ $plan->name }} |
| {{ __('Amount') }} | {{ $payment->currency }} {{ number_format($payment->amount, 2) }} |
| {{ __('Date') }} | {{ $payment->paid_at->format('Y-m-d') }} |
@endcomponent

{{ __('A copy of your receipt is attached to this email.') }}

{{ __('Thanks,') }}<br>
{{ config('app.name') }}
@endcomponent
