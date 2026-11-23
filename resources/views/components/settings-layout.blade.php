@props(['heading', 'subheading' => null])

<div class="mx-auto w-full max-w-3xl">
    <flux:heading size="xl" level="1">Settings</flux:heading>

    <flux:navbar class="mt-4 border-b border-zinc-200 dark:border-zinc-700">
        <flux:navbar.item href="{{ route('settings.profile') }}" :current="request()->routeIs('settings.profile')">Profile</flux:navbar.item>
        <flux:navbar.item href="{{ route('settings.password') }}" :current="request()->routeIs('settings.password')">Password</flux:navbar.item>
        <flux:navbar.item href="{{ route('settings.account') }}" :current="request()->routeIs('settings.account')">Account</flux:navbar.item>
    </flux:navbar>

    <div class="mt-8">
        <flux:heading size="lg" level="2">{{ $heading }}</flux:heading>

        @if ($subheading)
            <flux:text class="mt-1">{{ $subheading }}</flux:text>
        @endif

        <div class="mt-6">
            {{ $slot }}
        </div>
    </div>
</div>
