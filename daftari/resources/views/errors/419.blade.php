<x-error-page code="419" :title="__('Page expired')" :message="__('Your session took too long and the page expired. Please go back and try again.')">
    <a href="javascript:history.back()" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Go back') }}</a>
    <a href="{{ route('home') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Go to homepage') }}</a>
</x-error-page>
