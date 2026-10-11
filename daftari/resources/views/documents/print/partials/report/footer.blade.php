{{-- Thin footer note: company contact + generation timestamp. --}}
<div class="footer-note">
    @if ($company->phone){{ __('Phone') }}: {{ $company->phone }}&nbsp;&nbsp;@endif
    @if ($company->vat_number){{ __('VAT No.') }}: {{ $company->vat_number }}&nbsp;&nbsp;@endif
    {{ __('Generated') }}: {{ \App\Support\PlatformFormat::date(now()) }}
</div>
