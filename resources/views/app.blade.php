<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />

    <title inertia>{{ config('app.name', 'Skor Saham') }}</title>

    <meta name="theme-color" content="#0f172a" />
    <link rel="manifest" href="/manifest.json" />
    <link rel="icon" href="/icons/icon-192.png" />
    <link rel="apple-touch-icon" href="/icons/icon-192.png" />

    @vite(['resources/css/app.css', 'resources/js/app.tsx'])
    @inertiaHead
</head>
<body class="antialiased">
    @inertia
</body>
</html>
