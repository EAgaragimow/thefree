<x-layouts.public title="Find vetted freelancers" bare>
    <section class="bg-ink text-white">
        <div class="mx-auto w-full max-w-7xl px-4 py-20 sm:px-6 sm:py-28">
            <div class="max-w-3xl">
                <p class="text-xs font-semibold uppercase tracking-widest text-ember">Freelancers &middot; Projects &middot; Expert calls</p>
                <h1 class="font-display mt-5 text-5xl leading-tight sm:text-6xl">Hire vetted freelancers. Book expert calls.</h1>
                <p class="mt-6 max-w-2xl text-lg leading-relaxed text-white/75">
                    {{ config('app.name') }} connects brands with professional freelancers for projects and scheduled online consultations.
                </p>

                <div class="mt-10 flex flex-wrap gap-3">
                    @auth
                        <a href="{{ route('dashboard') }}" class="btn-leaf">Go to dashboard</a>
                    @else
                        <a href="{{ route('register') }}" class="btn-leaf">Sign up as a Client</a>
                        <a href="{{ route('freelancer.apply') }}" class="btn-outline-light">Become a Freelancer</a>
                    @endauth
                </div>
            </div>
        </div>
    </section>

    <section class="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 sm:py-8">
        <div class="bg-white p-6 sm:p-12">
            <h2 class="font-display text-3xl sm:text-4xl">How it works</h2>

            <div class="mt-10 grid gap-10 md:grid-cols-3">
                <div class="space-y-3 border-t-2 border-leaf pt-5">
                    <p class="text-sm font-semibold uppercase tracking-widest text-leaf">01</p>
                    <h3 class="font-display text-2xl">Find the right expert</h3>
                    <p class="leading-relaxed text-zinc-600">Browse a directory of vetted freelancers and filter by function, role, skills, tools and industry.</p>
                </div>

                <div class="space-y-3 border-t-2 border-ember pt-5">
                    <p class="text-sm font-semibold uppercase tracking-widest text-ember">02</p>
                    <h3 class="font-display text-2xl">Talk it through</h3>
                    <p class="leading-relaxed text-zinc-600">Open a profile, see real work and services, then book a free or paid consultation call.</p>
                </div>

                <div class="space-y-3 border-t-2 border-ink pt-5">
                    <p class="text-sm font-semibold uppercase tracking-widest text-ink">03</p>
                    <h3 class="font-display text-2xl">Start the project</h3>
                    <p class="leading-relaxed text-zinc-600">Agree the scope, work together and keep everything in one place, from the first message to delivery.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="mx-auto w-full max-w-7xl px-4 pb-6 sm:px-6 sm:pb-8">
        <div class="grid gap-4 md:grid-cols-2">
            <div class="bg-white p-6 sm:p-10">
                <p class="text-xs font-semibold uppercase tracking-widest text-leaf">For brands</p>
                <h2 class="font-display mt-3 text-3xl">Hire with confidence</h2>
                <p class="mt-4 leading-relaxed text-zinc-600">Every freelancer is reviewed before they appear in the directory. Start with a free plan or upgrade for full profiles, the complete directory and consultation booking.</p>
                @guest
                    <a href="{{ route('register') }}" class="btn-leaf mt-8">Sign up as a Client</a>
                @endguest
            </div>

            <div class="bg-white p-6 sm:p-10">
                <p class="text-xs font-semibold uppercase tracking-widest text-ember">For freelancers</p>
                <h2 class="font-display mt-3 text-3xl">Be found by the right brands</h2>
                <p class="mt-4 leading-relaxed text-zinc-600">Apply once, build a profile with highlights, services and consultations, and let brands come to you.</p>
                @guest
                    <a href="{{ route('freelancer.apply') }}" class="btn-leaf mt-8">Become a Freelancer</a>
                @endguest
            </div>
        </div>
    </section>
</x-layouts.public>
