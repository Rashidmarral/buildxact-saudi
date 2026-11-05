@php($message = __('You don\'t have permission to view this page.'))
<x-error-page code="403" :title="__('Access denied')" :message="$message">
    <a href="{{ route('home') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Go to homepage') }}</a>
</x-error-page>
