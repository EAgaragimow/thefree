@props(['title' => null])

@php
    $user = auth()->user();
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @fluxAppearance
    </head>
    <body class="min-h-screen bg-white antialiased dark:bg-zinc-800">
        <flux:sidebar sticky collapsible="mobile" class="border-r border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <flux:sidebar.brand href="{{ route('dashboard') }}" name="{{ config('app.name') }}" />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                @if ($user->isClient())
                    <flux:sidebar.item icon="home" href="{{ route('client.dashboard') }}" :current="request()->routeIs('client.dashboard')">Dashboard</flux:sidebar.item>
                    <flux:sidebar.item icon="users" href="{{ route('directory.index') }}" :current="request()->routeIs('directory.*')">Freelancers</flux:sidebar.item>
                    <flux:sidebar.item icon="video-camera" href="{{ route('client.calls') }}" :current="request()->routeIs('client.calls')">Calls</flux:sidebar.item>
                    <flux:sidebar.item icon="briefcase" href="{{ route('client.projects') }}" :current="request()->routeIs('client.projects')">Projects</flux:sidebar.item>
                    <flux:sidebar.item icon="heart" href="{{ route('client.favorites') }}" :current="request()->routeIs('client.favorites')">Favourites</flux:sidebar.item>
                    <flux:sidebar.item icon="sparkles" href="{{ route('client.recommended') }}" :current="request()->routeIs('client.recommended')">Recommended</flux:sidebar.item>
                    <flux:sidebar.item icon="building-office" href="{{ route('client.brand-profile') }}" :current="request()->routeIs('client.brand-profile')">Brand settings</flux:sidebar.item>
                @elseif ($user->isFreelancer())
                    <flux:sidebar.item icon="home" href="{{ route('freelancer.dashboard') }}" :current="request()->routeIs('freelancer.dashboard')">Dashboard</flux:sidebar.item>
                    <flux:sidebar.group expandable icon="user-circle" heading="Profile settings" :expanded="request()->routeIs('freelancer.profile', 'freelancer.highlights', 'freelancer.services', 'freelancer.consultations')">
                        <flux:sidebar.item href="{{ route('freelancer.profile') }}" :current="request()->routeIs('freelancer.profile')">My profile</flux:sidebar.item>
                        <flux:sidebar.item href="{{ route('freelancer.highlights') }}" :current="request()->routeIs('freelancer.highlights')">Highlights</flux:sidebar.item>
                        <flux:sidebar.item href="{{ route('freelancer.services') }}" :current="request()->routeIs('freelancer.services')">Services</flux:sidebar.item>
                        <flux:sidebar.item href="{{ route('freelancer.consultations') }}" :current="request()->routeIs('freelancer.consultations')">Consultations</flux:sidebar.item>
                    </flux:sidebar.group>
                    <flux:sidebar.item icon="eye" href="{{ route('freelancer.preview') }}" :current="request()->routeIs('freelancer.preview')">Preview</flux:sidebar.item>
                @else
                    <flux:sidebar.item icon="shield-check" href="{{ url('/admin') }}">Admin panel</flux:sidebar.item>
                @endif
            </flux:sidebar.nav>

            <flux:sidebar.spacer />

            <flux:sidebar.nav>
                <flux:sidebar.item icon="cog-6-tooth" href="{{ route('settings.profile') }}" :current="request()->routeIs('settings.*')">Settings</flux:sidebar.item>
            </flux:sidebar.nav>

            <flux:dropdown position="top" align="start">
                <flux:sidebar.profile name="{{ $user->name }}" :avatar="$user->avatarUrl()" />

                <flux:menu>
                    <flux:menu.item icon="cog-6-tooth" href="{{ route('settings.profile') }}">Settings</flux:menu.item>
                    <flux:menu.separator />
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle">Log out</flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:sidebar>

        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />
            <flux:spacer />
        </flux:header>

        <flux:main container>
            {{ $slot }}
        </flux:main>

        @fluxScripts
    </body>
</html>
