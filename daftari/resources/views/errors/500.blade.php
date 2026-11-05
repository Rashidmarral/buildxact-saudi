@php($message = __('An unexpected error occurred on our end. We\'ve been notified and are looking into it.'))
<x-error-page code="500" :title="__('Something went wrong')" :message="$message">
    <a href="{{ route('home') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Go to homepage') }}</a>
</x-error-page>
