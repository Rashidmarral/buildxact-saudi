@component('mail::message')
# {{ __('New partner application') }}

**{{ __('Name') }}:** {{ $partner->name }} ({{ $partner->email }})
@if ($partner->phone)
**{{ __('Phone') }}:** {{ $partner->phone }}
@endif
@if ($partner->company_name)
**{{ __('Company') }}:** {{ $partner->company_name }}
@endif
@if ($partner->partnerType)
**{{ __('Requested type') }}:** {{ $partner->partnerType->name() }}
@endif

@if ($partner->message)
{{ $partner->message }}
@endif

@component('mail::button', ['url' => route('admin.partners.show', $partner)])
{{ __('Review application') }}
@endcomponent
@endcomponent
