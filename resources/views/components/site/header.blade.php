{{--
    The black site header. Links depend on who is signed in: guests see the way in, a brand, a
    freelancer or an admin sees the pages meant for them. Everything else (settings, brand settings,
    log out) is in the account menu.
--}}
@php
    $user = auth()->user();

    /** @var list<array{label: string, href: string, current: bool}> $links */
    $links = match (true) {
        $user?->isClient() => [
            ['label' => 'Dashboard', 'href' => route('client.dashboard'), 'current' => request()->routeIs('client.*')],
            ['label' => 'Messages', 'href' => route('messages.index'), 'current' => request()->routeIs('messages.*')],
            ['label' => 'Freelancers', 'href' => route('directory.index'), 'current' => request()->routeIs('directory.*')],
            ['label' => 'Blog', 'href' => route('blog.index'), 'current' => request()->routeIs('blog.*')],
        ],
        $user?->isFreelancer() => [
            ['label' => 'Dashboard', 'href' => route('freelancer.dashboard'), 'current' => request()->routeIs('freelancer.*') && ! request()->routeIs('freelancer.preview')],
            ['label' => 'Profile', 'href' => route('freelancer.preview'), 'current' => request()->routeIs('freelancer.preview')],
            ['label' => 'Messages', 'href' => route('messages.index'), 'current' => request()->routeIs('messages.*')],
        ],
        $user?->isAdmin() => [
            ['label' => 'Admin panel', 'href' => url('/admin'), 'current' => false],
            ['label' => 'Freelancers', 'href' => route('directory.index'), 'current' => request()->routeIs('directory.*')],
            ['label' => 'Blog', 'href' => route('blog.index'), 'current' => request()->routeIs('blog.*')],
            ['label' => 'Messages', 'href' => route('messages.index'), 'current' => request()->routeIs('messages.*')],
        ],
        default => [],
    };

    $linkClasses = 'border-b-2 py-2 text-sm font-semibold uppercase tracking-wide text-white transition hover:border-white/40';
@endphp

<header x-data="{ open: false }" class="bg-ink text-white">
    <div class="mx-auto flex h-20 w-full max-w-7xl items-center gap-8 px-4 sm:px-6">
        <a href="{{ route('home') }}" class="text-xl font-semibold tracking-tight text-white">{{ config('app.name') }}</a>

        <nav class="ms-auto hidden items-center gap-8 md:flex" aria-label="Main">
            @foreach ($links as $link)
                <a href="{{ $link['href'] }}" class="{{ $linkClasses }} {{ $link['current'] ? 'border-leaf' : 'border-transparent' }}">{{ $link['label'] }}</a>
            @endforeach

            @auth
                <flux:dropdown position="bottom" align="end">
                    <button type="button" class="flex items-center gap-2 py-2 text-sm font-semibold uppercase tracking-wide text-white">
                        <flux:avatar size="xs" :src="$user->avatarUrl()" :name="$user->name" />
                        <span class="max-w-40 truncate">{{ $user->name }}</span>
                        <flux:icon.chevron-down variant="micro" />
                    </button>

                    <flux:menu>
                        <flux:menu.item icon="cog-6-tooth" href="{{ route('settings.profile') }}">Settings</flux:menu.item>
                        @if ($user->isClient())
                            <flux:menu.item icon="heart" href="{{ route('client.favorites') }}">Favourites</flux:menu.item>
                            <flux:menu.item icon="sparkles" href="{{ route('client.recommended') }}">Recommended</flux:menu.item>
                            <flux:menu.item icon="building-office" href="{{ route('client.brand-profile') }}">Brand settings</flux:menu.item>
                        @elseif ($user->isFreelancer())
                            <flux:menu.item icon="user-circle" href="{{ route('freelancer.profile') }}">Profile settings</flux:menu.item>
                        @endif
                        <flux:menu.separator />
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle">Log out</flux:menu.item>
                        </form>
                    </flux:menu>
                </flux:dropdown>
            @else
                <a href="{{ route('freelancer.apply') }}" class="{{ $linkClasses }} border-transparent">Become a Freelancer</a>
                <a href="{{ route('login') }}" class="{{ $linkClasses }} border-transparent">Log in</a>
                <a href="{{ route('register') }}" class="btn-outline-light">Sign up</a>
            @endauth
        </nav>

        <button type="button" class="ms-auto p-2 md:hidden" x-on:click="open = ! open" x-bind:aria-expanded="open" aria-label="Menu">
            <flux:icon.bars-3 x-show="! open" />
            <flux:icon.x-mark x-show="open" x-cloak />
        </button>
    </div>

    <nav x-show="open" x-cloak x-collapse class="border-t border-white/15 md:hidden" aria-label="Mobile">
        <div class="mx-auto flex w-full max-w-7xl flex-col px-4 py-3 sm:px-6">
            @foreach ($links as $link)
                <a href="{{ $link['href'] }}" class="py-3 text-sm font-semibold uppercase tracking-wide {{ $link['current'] ? 'text-leaf' : 'text-white' }}">{{ $link['label'] }}</a>
            @endforeach

            @auth
                <a href="{{ route('settings.profile') }}" class="py-3 text-sm font-semibold uppercase tracking-wide text-white">Settings</a>
                @if ($user->isClient())
                    <a href="{{ route('client.favorites') }}" class="py-3 text-sm font-semibold uppercase tracking-wide text-white">Favourites</a>
                    <a href="{{ route('client.recommended') }}" class="py-3 text-sm font-semibold uppercase tracking-wide text-white">Recommended</a>
                    <a href="{{ route('client.brand-profile') }}" class="py-3 text-sm font-semibold uppercase tracking-wide text-white">Brand settings</a>
                @elseif ($user->isFreelancer())
                    <a href="{{ route('freelancer.profile') }}" class="py-3 text-sm font-semibold uppercase tracking-wide text-white">Profile settings</a>
                @endif
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="py-3 text-sm font-semibold uppercase tracking-wide text-white">Log out</button>
                </form>
            @else
                <a href="{{ route('freelancer.apply') }}" class="py-3 text-sm font-semibold uppercase tracking-wide text-white">Become a Freelancer</a>
                <a href="{{ route('login') }}" class="py-3 text-sm font-semibold uppercase tracking-wide text-white">Log in</a>
                <a href="{{ route('register') }}" class="py-3 text-sm font-semibold uppercase tracking-wide text-white">Sign up</a>
            @endauth
        </div>
    </nav>
</header>
