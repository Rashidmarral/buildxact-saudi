<x-error-page code="403" :title="__('New signups are currently closed')" :message="__('Already have an account? Log in instead.')">
    <a href="{{ route('login') }}" class="btn btn-primary">{{ __('Log in') }}</a>
</x-error-page>
