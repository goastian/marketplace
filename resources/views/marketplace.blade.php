<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $surface === 'admin' ? 'Admin · ' : '' }}{{ config('app.name', 'Midori Marketplace') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div id="marketplace-app" data-surface="{{ $surface ?? 'storefront' }}"></div>
</body>
</html>