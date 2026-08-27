<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Login — {{ config('company.name') }}</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 24 24%22 fill=%22%23173c36%22><rect x=%223%22 y=%223%22 width=%2218%22 height=%2218%22 rx=%225%22/><path d=%22M8 12h8M12 8v8%22 stroke=%22%23e35f38%22 stroke-width=%222%22/></svg>">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen items-center justify-center bg-teal-950 bg-teal-pattern px-4">

    <div class="w-full max-w-sm">
        <div class="mb-8 text-center">
            @if (config('company.logo_path'))
                <img src="{{ asset('storage/'.config('company.logo_path')) }}" alt="{{ config('company.short_name') }}" class="mx-auto h-11 w-auto object-contain">
            @else
                <span class="text-2xl font-bold text-white">{{ config('company.short_name', config('company.name')) }}</span>
            @endif
            <p class="mt-1 text-sm text-teal-200">Admin Panel Login</p>
        </div>

        <div class="rounded-2xl bg-white p-8 shadow-xl">
            @if ($errors->any())
                <div class="mb-5 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('admin.login.attempt') }}" class="space-y-4">
                @csrf

                <x-admin.field label="Email Address" name="email" type="email" :value="old('email')" required autofocus />
                <x-admin.field label="Password" name="password" type="password" required />

                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <input type="checkbox" name="remember" value="1" class="h-4 w-4 rounded border-slate-300 text-teal-600 focus:ring-teal-500">
                    Remember me
                </label>

                <button type="submit" class="w-full rounded-lg bg-teal-800 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-teal-900">
                    Log In
                </button>
            </form>
        </div>

        <p class="mt-6 text-center text-xs text-teal-300">
            <a href="{{ route('home') }}" class="hover:text-white">&larr; Back to website</a>
        </p>
    </div>

</body>
</html>
