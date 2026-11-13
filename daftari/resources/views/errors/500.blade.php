@php($message = $message ?? __('An unexpected error occurred on our end. We\'ve been notified and are looking into it.'))
<x-error-page code="500" :title="__('Something went wrong')" :message="$message">
    <a href="javascript:location.reload()" class="btn btn-ghost">{{ __('Try again') }}</a>
    <a href="{{ route('home') }}" class="btn btn-primary">{{ __('Go to homepage') }}</a>
</x-error-page>
