@component('mail::message')
@if ($tier >= 3)
# {{ __('Final notice') }}

{{ __('Dear :name,', ['name' => $client->name]) }}

{{ __('Invoice :number from :company is now :days day(s) past its due date and remains unpaid despite earlier reminders. Please settle this balance immediately to avoid further action.', ['number' => $invoice->invoice_number, 'company' => $company->name, 'days' => $daysOverdue]) }}
@elseif ($tier === 2)
# {{ __('Overdue notice') }}

{{ __('Dear :name,', ['name' => $client->name]) }}

{{ __('Invoice :number from :company is now :days day(s) overdue. This is a second reminder — please arrange payment as soon as possible.', ['number' => $invoice->invoice_number, 'company' => $company->name, 'days' => $daysOverdue]) }}
@else
# {{ __('Payment reminder') }}

{{ __('Dear :name,', ['name' => $client->name]) }}

{{ __('This is a friendly reminder that invoice :number from :company is now :days day(s) past its due date.', ['number' => $invoice->invoice_number, 'company' => $company->name, 'days' => $daysOverdue]) }}
@endif

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
