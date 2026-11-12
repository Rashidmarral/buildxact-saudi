<x-error-page code="401" :title="__('Authentication required')" :message="__('Please log in to continue.')">
    <a href="{{ route('login') }}" class="btn btn-primary">{{ __('Log in') }}</a>
</x-error-page>
