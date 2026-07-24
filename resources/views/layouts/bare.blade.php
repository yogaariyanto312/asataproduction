<!DOCTYPE html>
<html lang="id" class="h-full dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Asata Production') — Asata System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script>
        window.APP_BASE = window.location.origin + '{{ rtrim(parse_url(url("/"), PHP_URL_PATH) ?? "", "/") }}';
    </script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    @stack('styles')
    <style>
        /* Latar transparan supaya menyatu dengan modal induk */
        html, body { background: transparent !important; }
        body { padding: 0; }
    </style>
</head>
<body class="font-sans antialiased text-slate-800 dark:text-white">
    <div id="main-content">
        @yield('content')
    </div>
    @stack('scripts')
</body>
</html>
