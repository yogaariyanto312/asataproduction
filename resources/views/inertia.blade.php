<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- Pilihan sidebar kecil harus berlaku sebelum CSS dimuat, kalau tidak sidebar berkedip. --}}
    <script>try { if (localStorage.getItem('qc:sidebar-mini') === '1') document.documentElement.classList.add('sb-mini'); } catch (_) {}</script>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="csrf-refresh" content="{{ route('csrf.token') }}">

    <title inertia>{{ config('app.name', 'Asata Production') }}</title>

    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="alternate icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <meta name="sw-url" content="{{ asset('sw.js') }}">

    {{-- Inter disamakan dengan layouts/auth.blade.php & layouts/app.blade.php,
         karena --font-sans di resources/css/app.css memang menunjuk Inter. --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Sora:wght@500;600;700;800&display=swap" rel="stylesheet">

    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/inertia.jsx'])
    @inertiaHead
</head>
<body class="h-full font-sans antialiased bg-slate-900 text-slate-100">
    @inertia
</body>
</html>
