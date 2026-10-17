@php($message = __('The page you\'re looking for doesn\'t exist or may have moved.'))
<x-error-page code="404" :title="__('Page not found')" :message="$message">
    <a href="{{ route('home') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Go to homepage') }}</a>
</x-error-page>
