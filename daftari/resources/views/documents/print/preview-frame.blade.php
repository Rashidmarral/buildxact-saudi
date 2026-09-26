<!DOCTYPE html>
{{--
    Standalone, chrome-free page for the invoice-template gallery's card
    thumbnails — no header/sidebar, just the real documents.print.body
    partial with sample data, embedded in an iframe and scaled down via
    CSS on the gallery page. Never linked to directly by a real user.
--}}
<html lang="en" dir="ltr">
<head>
    <meta charset="utf-8">
    <title>{{ __('Preview') }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])
</head>
<body class="bg-white">
    <div class="p-8">
        @include('documents.print.body', ['doc' => $doc, 'company' => $company, 'template' => $template])
    </div>
</body>
</html>
