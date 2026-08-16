{{--
    Self-contained wrapper for server-side voucher PDF rendering (see
    App\Services\ChromiumPdfRenderer). Mirrors documents/print/standalone
    but wraps the chrome-header/voucher-body/chrome-footer trio vouchers
    actually use instead of the line-item documents.print.body partial.
--}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8">
<style>
    {!! $inlineCss !!}
    body { background: #fff; padding: 0; margin: 0; }
</style>
</head>
<body>
    <div class="bg-white max-w-xl">
        @include('documents.print.chrome-header', ['company' => $company, 'template' => $template])
        @include('documents.print.voucher-body', ['voucher' => $voucher, 'type' => $type])
        @include('documents.print.chrome-footer', ['company' => $company, 'template' => $template])
    </div>
</body>
</html>
