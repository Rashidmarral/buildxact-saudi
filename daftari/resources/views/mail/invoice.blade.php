@component('mail::message')
# {{ __('Invoice :number', ['number' => $invoice->invoice_number]) }}

{{ __('Dear :name,', ['name' => $client->name]) }}

{{ __('Please find attached invoice :number from :company.', ['number' => $invoice->invoice_number, 'company' => $company->name]) }}

@component('mail::table')
| | |
|:---|---:|
| {{ __('Issue date') }} | {{ $invoice->issue_date->format('Y-m-d') }} |
@if ($invoice->due_date)
| {{ __('Due date') }} | {{ $invoice->due_date->format('Y-m-d') }} |
@endif
| {{ __('Subtotal') }} | {{ \App\Support\Money::format($invoice->subtotal) }} |
| {{ __('VAT') }} | {{ \App\Support\Money::format($invoice->vat_total) }} |
| **{{ __('Total') }}** | **{{ \App\Support\Money::format($invoice->total) }}** |
| {{ $invoice->balanceDue() < 0 ? __('Overpaid') : __('Balance due') }} | {{ \App\Support\Money::format(abs($invoice->balanceDue())) }} |
@endcomponent

{{ __('The full invoice, including the ZATCA QR code, is attached as a PDF.') }}

@component('mail::button', ['url' => route('public.invoices.show', ['id' => $invoice->id, 'token' => $invoice->public_token])])
{{ __('View & pay online') }}
@endcomponent

{{ __('Thank you for your business.') }}<br>
{{ $company->name }}
@endcomponent
