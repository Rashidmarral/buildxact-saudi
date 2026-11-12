<x-error-page code="503" :title="__('Daftari is briefly offline for maintenance')" :message="$message ?: __('We\'ll be back shortly. Thanks for your patience.')">
    <a href="javascript:location.reload()" class="btn btn-primary">{{ __('Try again') }}</a>
</x-error-page>
