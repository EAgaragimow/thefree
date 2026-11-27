@props([
    'title' => null,
    'tabs' => null,
    'panel' => true,
    'bare' => false,
])

{{--
    The page shell shared by everything except the admin panel: header, optional tab bar, content and
    footer. `panel` puts the content in a white block on the grey page; `bare` leaves the content
    alone (the home page lays out its own full-width sections).
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="light">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@400;500;600&display=swap" rel="stylesheet">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="flex min-h-screen flex-col bg-page font-sans text-ink antialiased">
        <x-site.header />

        @if ($tabs)
            <x-site.tabs :items="$tabs" />
        @endif

        <main class="flex-1">
            @if ($bare)
                {{ $slot }}
            @else
                <div class="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 sm:py-8">
                    @if ($panel)
                        <div class="bg-white p-6 sm:p-10">
                            {{ $slot }}
                        </div>
                    @else
                        {{ $slot }}
                    @endif
                </div>
            @endif
        </main>

        <x-site.footer />

        @fluxScripts
    </body>
</html>
