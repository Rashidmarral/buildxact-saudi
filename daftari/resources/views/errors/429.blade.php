<x-error-page code="429" :title="__('Too many requests')" :message="__('Please wait a moment and try again.')">
    <a href="{{ route('home') }}" class="btn btn-primary">{{ __('Go to homepage') }}</a>
</x-error-page>
