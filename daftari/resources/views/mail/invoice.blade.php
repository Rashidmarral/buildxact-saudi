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
| {{ __('Subtotal') }} | SAR {{ number_format($invoice->subtotal, 2) }} |
| {{ __('VAT') }} | SAR {{ number_format($invoice->vat_total, 2) }} |
| **{{ __('Total') }}** | **SAR {{ number_format($invoice->total, 2) }}** |
| {{ __('Balance due') }} | SAR {{ number_format($invoice->balanceDue(), 2) }} |
@endcomponent

{{ __('The full invoice, including the ZATCA QR code, is attached as a PDF.') }}

{{ __('Thank you for your business.') }}<br>
{{ $company->name }}
@endcomponent
