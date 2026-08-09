<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="{{ asset('images/cspc.webp') }}" type="image/webp">

    <!-- Google Fonts Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        body, button, input, select, textarea, a, span, div, h1, h2, h3, h4, h5, h6, label {
            font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
        }
        ::-webkit-scrollbar {
            display: none !important;
            width: 0 !important;
            height: 0 !important;
        }
        * {
            -ms-overflow-style: none !important;
            scrollbar-width: none !important;
        }
    </style>

    <title>{{ $title ?? config('app.name') }}</title>

    @stack('styles')
    @livewireStyles
</head>
<body>
    {{ $slot }}

    @stack('scripts')
    @livewireScripts
</body>
</html>