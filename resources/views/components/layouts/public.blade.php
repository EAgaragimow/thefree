@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @fluxAppearance
    </head>
    <body class="min-h-screen bg-white text-zinc-900 antialiased dark:bg-zinc-900 dark:text-zinc-100">
        <flux:header container class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:brand href="{{ route('home') }}" name="{{ config('app.name') }}" />

            <flux:spacer />

            <flux:navbar>
                <flux:navbar.item href="{{ route('freelancer.apply') }}">Become a Freelancer</flux:navbar.item>

                @auth
                    <flux:navbar.item href="{{ route('dashboard') }}">Dashboard</flux:navbar.item>
                @else
                    <flux:navbar.item href="{{ route('login') }}">Log in</flux:navbar.item>
                    <flux:navbar.item href="{{ route('register') }}">Sign up</flux:navbar.item>
                @endauth
            </flux:navbar>
        </flux:header>

        <flux:main container>
            {{ $slot }}
        </flux:main>

        @fluxScripts
    </body>
</html>
