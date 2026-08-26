@component('mail::message')
# {{ __('Payment reminder') }}

{{ __('Dear :name,', ['name' => $client->name]) }}

{{ __('This is a friendly reminder that invoice :number from :company is now :days day(s) past its due date.', ['number' => $invoice->invoice_number, 'company' => $company->name, 'days' => $daysOverdue]) }}

@component('mail::table')
| | |
|:---|---:|
| {{ __('Issue date') }} | {{ $invoice->issue_date->format('Y-m-d') }} |
| {{ __('Due date') }} | {{ $invoice->due_date->format('Y-m-d') }} |
| {{ __('Total') }} | {{ \App\Support\Money::format($invoice->total) }} |
| **{{ __('Balance due') }}** | **{{ \App\Support\Money::format($invoice->balanceDue()) }}** |
@endcomponent

{{ __('Please arrange payment at your earliest convenience. If you have already paid, kindly disregard this reminder.') }}

@component('mail::button', ['url' => route('public.invoices.show', ['id' => $invoice->id, 'token' => $invoice->public_token])])
{{ __('View & pay online') }}
@endcomponent

{{ __('Thank you for your business.') }}<br>
{{ $company->name }}
@endcomponent
