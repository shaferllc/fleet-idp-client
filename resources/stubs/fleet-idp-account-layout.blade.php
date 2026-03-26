{{-- Published by: php artisan vendor:publish --tag=fleet-idp-account-layout --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name'))</title>
    @stack('styles')
</head>
<body class="font-sans antialiased">
<div class="min-h-screen flex flex-col items-center justify-center px-4 py-10 bg-gray-50 dark:bg-gray-900">
    <div class="w-full max-w-md rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-6 shadow-sm">
        @yield('content')
    </div>
</div>
@stack('scripts')
</body>
</html>
