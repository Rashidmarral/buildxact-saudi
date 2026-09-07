@php($message = __('We\'re briefly offline. Please check back in a few minutes.'))
<x-error-page code="503" :title="__('Service unavailable')" :message="$message">
    <a href="{{ route('home') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Go to homepage') }}</a>
</x-error-page>
