<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ trans('fleet-idp::oauth.failure_title') }}</title>
    <style>
        :root { color-scheme: light dark; }
        body {
            font-family: ui-sans-serif, system-ui, sans-serif;
            line-height: 1.5;
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            background: #f8f8f6;
            color: #1a1a1a;
        }
        @media (prefers-color-scheme: dark) {
            body { background: #141414; color: #f0f0f0; }
            a { color: #7cb87c; }
        }
        main {
            max-width: 28rem;
            width: 100%;
            padding: 1.75rem;
            border-radius: 0.75rem;
            border: 1px solid rgba(0,0,0,.08);
            background: #fff;
            box-shadow: 0 1px 3px rgba(0,0,0,.06);
        }
        @media (prefers-color-scheme: dark) {
            main {
                background: #1f1f1f;
                border-color: rgba(255,255,255,.1);
            }
        }
        h1 { font-size: 1.125rem; font-weight: 600; margin: 0 0 0.75rem; }
        p { margin: 0 0 1rem; font-size: 0.9375rem; color: #444; }
        @media (prefers-color-scheme: dark) {
            p { color: #c8c8c8; }
        }
        a {
            display: inline-block;
            font-size: 0.9375rem;
            font-weight: 600;
            color: #2d6a4f;
            text-decoration: none;
        }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <main>
        <h1>{{ trans('fleet-idp::oauth.failure_title') }}</h1>
        <p>{{ $message }}</p>
        <p><a href="{{ $tryAgainUrl }}">{{ trans('fleet-idp::oauth.try_again') }}</a></p>
    </main>
</body>
</html>
