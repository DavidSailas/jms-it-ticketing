<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('images/logo.png') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="auth-backdrop font-sans antialiased text-slate-800">
{{-- Decorative backdrop. Fixed, so it never adds height or a scrollbar. --}}
<div class="auth-circuit pointer-events-none fixed inset-0" aria-hidden="true"></div>

{{-- One centred card. It only grows as tall as its content, so a normal screen never scrolls. --}}
<main class="relative flex min-h-[100dvh] flex-col items-center justify-center px-4 py-6">
    <div class="relative w-full max-w-[26rem] overflow-hidden rounded-2xl bg-white p-6 shadow-xl shadow-brand-900/10 ring-1 ring-brand-900/5 sm:p-8 [@media(max-height:700px)]:p-5">
        <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-brand-800 via-brand-600 to-brand-400" aria-hidden="true"></div>

        <img src="{{ asset('images/logo.png') }}" alt="JMS One IT" class="mx-auto mt-1 h-20 w-auto mix-blend-multiply [@media(max-height:700px)]:h-16">

        {{ $slot }}
    </div>

    <p class="mt-5 text-xs text-slate-500 [@media(max-height:700px)]:hidden">&copy; {{ date('Y') }} JMS One IT &middot; make IT happen</p>
</main>
</body>
</html>
