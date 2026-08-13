<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · Daftari</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 flex flex-col items-center justify-center px-4 py-12">
    <a href="{{ route('home') }}" class="flex items-center gap-2 font-bold text-xl text-brand-800 mb-8">
        <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-brand-600 text-white">د</span>
        Daftari
    </a>

    <div class="w-full max-w-md bg-white rounded-2xl shadow-sm border border-slate-100 p-8">
        @if ($errors->any())
            <div class="mb-5 rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">
                <ul class="list-disc ps-4 space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </div>

    <a href="{{ route('locale.switch', app()->getLocale() === 'ar' ? 'en' : 'ar') }}" class="mt-6 text-sm text-slate-500 hover:text-brand-700">
        {{ app()->getLocale() === 'ar' ? 'View in English' : 'عرض بالعربية' }}
    </a>
</body>
</html>
