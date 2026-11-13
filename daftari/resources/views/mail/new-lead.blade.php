@component('mail::message')
# {{ __('New lead') }}

**{{ __('Name') }}:** {{ $lead->name }} ({{ $lead->email }})
@if ($lead->phone)
**{{ __('Phone') }}:** {{ $lead->phone }}
@endif
@if ($lead->company_name)
**{{ __('Company') }}:** {{ $lead->company_name }}
@endif
@if ($lead->industryLabel())
**{{ __('Industry') }}:** {{ $lead->industryLabel() }}
@endif
**{{ __('Source') }}:** {{ $lead->sourceLabel() }}

@if ($lead->message)
{{ $lead->message }}
@endif

@component('mail::button', ['url' => route('admin.leads.show', $lead)])
{{ __('View in CRM') }}
@endcomponent
@endcomponent
