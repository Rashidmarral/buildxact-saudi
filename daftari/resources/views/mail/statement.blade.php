@component('mail::message')
# {{ __('Statement of Account') }}

{{ __('Dear :name,', ['name' => $client->name]) }}

{{ __('Please find attached your statement of account from :company.', ['company' => $company->name]) }}

@component('mail::table')
| | |
|:---|---:|
| **{{ $closingBalance < 0 ? __('Credit balance') : __('Balance due') }}** | **{{ \App\Support\Money::format(abs($closingBalance)) }}** |
@endcomponent

{{ __('Please contact us if you have any questions about this statement.') }}

{{ __('Thank you for your business.') }}<br>
{{ $company->name }}
@endcomponent
