<x-error-page code="429" :title="__('Too many requests')" :message="__('Please wait a moment and try again.')">
    <a href="{{ route('home') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Go to homepage') }}</a>
</x-error-page>
