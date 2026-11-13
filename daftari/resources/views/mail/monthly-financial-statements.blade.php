@component('mail::message')
# {{ __('Financial Statements for :period', ['period' => $periodStart->format('F Y')]) }}

{{ __('Hello,') }}

{{ __('Attached are the Balance Sheet (as of :asOf) and Income Statement (for :from to :to) for :company.', ['asOf' => $periodEnd->format('Y-m-d'), 'from' => $periodStart->format('Y-m-d'), 'to' => $periodEnd->format('Y-m-d'), 'company' => $company->name]) }}

{{ __('These are generated automatically from your books as they stand today — review them in Daftari for the full breakdown and any comparison periods.') }}

{{ __('Thank you.') }}<br>
{{ $company->name }}
@endcomponent
