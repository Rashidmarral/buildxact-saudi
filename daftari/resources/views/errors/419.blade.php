<x-error-page code="419" :title="__('Page expired')" :message="__('Your session took too long and the page expired. Please go back and try again.')">
    <a href="javascript:history.back()" class="btn btn-ghost">{{ __('Go back') }}</a>
    <a href="{{ route('home') }}" class="btn btn-primary">{{ __('Go to homepage') }}</a>
</x-error-page>
