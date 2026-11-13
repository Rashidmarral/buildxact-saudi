@php($message = $message ?? __('We\'re briefly offline. Please check back in a few minutes.'))
<x-error-page code="503" :title="__('Service unavailable')" :message="$message">
    <a href="javascript:location.reload()" class="btn btn-primary">{{ __('Try again') }}</a>
</x-error-page>
