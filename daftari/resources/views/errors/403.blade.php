@php($message = __('You don\'t have permission to view this page.'))
<x-error-page code="403" :title="__('Access denied')" :message="$message">
    <a href="{{ route('home') }}" class="btn btn-primary">{{ __('Go to homepage') }}</a>
</x-error-page>
