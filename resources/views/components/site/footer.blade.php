@php
    $user = auth()->user();
@endphp

<footer class="bg-ink text-white/80">
    <div class="mx-auto grid w-full max-w-7xl gap-10 px-4 py-12 sm:px-6 md:grid-cols-3">
        <div class="space-y-3">
            <a href="{{ route('home') }}" class="text-xl font-semibold text-white">{{ config('app.name') }}</a>
            <p class="max-w-xs text-sm leading-relaxed">Vetted freelancers for projects and scheduled expert calls.</p>
        </div>

        <div class="space-y-3">
            <p class="text-xs font-semibold uppercase tracking-widest text-white/50">Explore</p>
            <ul class="space-y-2 text-sm">
                @auth
                    <li><a class="hover:text-white" href="{{ route('dashboard') }}">Dashboard</a></li>
                    @unless ($user->isFreelancer())
                        <li><a class="hover:text-white" href="{{ route('directory.index') }}">Freelancers</a></li>
                        <li><a class="hover:text-white" href="{{ route('blog.index') }}">Blog</a></li>
                    @endunless
                    <li><a class="hover:text-white" href="{{ route('messages.index') }}">Messages</a></li>
                @else
                    <li><a class="hover:text-white" href="{{ route('home') }}">Home</a></li>
                    <li><a class="hover:text-white" href="{{ route('freelancer.apply') }}">Become a Freelancer</a></li>
                @endauth
            </ul>
        </div>

        <div class="space-y-3">
            <p class="text-xs font-semibold uppercase tracking-widest text-white/50">Account</p>
            <ul class="space-y-2 text-sm">
                @auth
                    <li><a class="hover:text-white" href="{{ route('settings.profile') }}">Settings</a></li>
                @else
                    <li><a class="hover:text-white" href="{{ route('login') }}">Log in</a></li>
                    <li><a class="hover:text-white" href="{{ route('register') }}">Sign up</a></li>
                @endauth
            </ul>
        </div>
    </div>

    <div class="border-t border-white/10">
        <div class="mx-auto w-full max-w-7xl px-4 py-5 text-xs text-white/50 sm:px-6">
            &copy; {{ now()->year }} {{ config('app.name') }}. All rights reserved.
        </div>
    </div>
</footer>
