<x-layouts.public title="Find vetted freelancers">
    <section class="mx-auto max-w-3xl py-16 text-center">
        <flux:heading size="xl" level="1" class="text-4xl!">Hire vetted freelancers. Book expert calls.</flux:heading>

        <flux:text class="mt-4 text-lg">
            {{ config('app.name') }} connects brands with professional freelancers for projects and scheduled online consultations.
        </flux:text>

        <div class="mt-8 flex flex-wrap justify-center gap-3">
            @auth
                <flux:button variant="primary" href="{{ route('dashboard') }}">Go to dashboard</flux:button>
            @else
                <flux:button variant="primary" href="{{ route('register') }}">Sign up as a Client</flux:button>
                <flux:button href="{{ route('freelancer.apply') }}">Become a Freelancer</flux:button>
            @endauth
        </div>
    </section>
</x-layouts.public>
