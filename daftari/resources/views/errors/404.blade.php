@php($message = __('The page you\'re looking for doesn\'t exist or may have moved.'))
<x-error-page code="404" :title="__('Page not found')" :message="$message">
    <a href="{{ route('home') }}" class="btn btn-primary">{{ __('Go to homepage') }}</a>
</x-error-page>
