<x-error-page code="401" :title="__('Authentication required')" :message="__('Please log in to continue.')">
    <a href="{{ route('login') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Log in') }}</a>
</x-error-page>
