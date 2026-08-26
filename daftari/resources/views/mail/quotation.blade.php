@component('mail::message')
# {{ $typeLabel }} {{ $quotation->quotation_number }}

{{ __('Dear :name,', ['name' => $client->name]) }}

{{ __('Please find attached :type :number from :company.', ['type' => strtolower($typeLabel), 'number' => $quotation->quotation_number, 'company' => $company->name]) }}

@component('mail::table')
| | |
|:---|---:|
| {{ __('Issue date') }} | {{ $quotation->issue_date->format('Y-m-d') }} |
@if ($quotation->expiry_date)
| {{ __('Valid until') }} | {{ $quotation->expiry_date->format('Y-m-d') }} |
@endif
| {{ __('Subtotal') }} | {{ \App\Support\Money::format($quotation->subtotal) }} |
| {{ __('VAT') }} | {{ \App\Support\Money::format($quotation->vat_total) }} |
| **{{ __('Total') }}** | **{{ \App\Support\Money::format($quotation->total) }}** |
@endcomponent

{{ __('The full document is attached as a PDF.') }}

{{ __('Thank you for your business.') }}<br>
{{ $company->name }}
@endcomponent
