<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name'))</title>
    <style>
        body { font-family: ui-sans-serif, system-ui, sans-serif; margin: 2rem auto; max-width: 28rem; padding: 0 1rem; line-height: 1.5; }
        label { display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem; }
        input[type=email], input[type=password], input[type=text] { width: 100%; padding: 0.5rem 0.65rem; margin-bottom: 1rem; border: 1px solid #ccc; border-radius: 0.375rem; box-sizing: border-box; }
        button, .btn { display: inline-block; padding: 0.5rem 1rem; background: #2563eb; color: #fff; border: none; border-radius: 0.375rem; font-weight: 600; cursor: pointer; text-decoration: none; }
        .error { color: #b91c1c; font-size: 0.875rem; margin: -0.5rem 0 0.75rem; }
        .status { color: #15803d; font-size: 0.875rem; margin-bottom: 1rem; }
        a { color: #2563eb; }
    </style>
    @stack('styles')
</head>
<body>
    @yield('content')
</body>
</html>
