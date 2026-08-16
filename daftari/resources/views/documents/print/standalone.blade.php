{{--
    Self-contained wrapper used only for server-side PDF rendering (see
    App\Services\ChromiumPdfRenderer): inlines the compiled Tailwind CSS
    directly so the page needs no live asset server, then reuses the exact
    same documents.print.body partial the browser's own Print/PDF button
    renders — guaranteeing the download and the on-screen/print output are
    always the same design.
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
    <div class="bg-white">
        @include('documents.print.body', ['doc' => $doc, 'company' => $company, 'template' => $template])
    </div>
</body>
</html>
