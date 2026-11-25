<?php

use App\Enums\SubscriptionType;
use App\Models\BrandProfile;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard')] class extends Component {
    public function brandProfileComplete(): bool
    {
        $profile = auth()->user()->brandProfile;

        return $profile !== null
            && filled($profile->company_name)
            && filled($profile->description)
            && filled($profile->industry);
    }

    /**
     * The brand profile with what the dashboard shows of it, or null before it is filled in.
     */
    public function brand(): ?BrandProfile
    {
        return auth()->user()->brandProfile?->load(['industry', 'professionalFunctions', 'professionalRoles']);
    }

    public function isFreePlan(): bool
    {
        return auth()->user()->subscription_type === SubscriptionType::FreePlan;
    }
};
?>

<div class="space-y-8">
    <div>
        <flux:heading size="xl" level="1">Welcome, {{ auth()->user()->name }}</flux:heading>
        <flux:text class="mt-1">Your {{ $this->isFreePlan() ? 'Free' : 'Paid' }} client dashboard.</flux:text>
    </div>

    @if ($this->isFreePlan())
        <flux:callout variant="warning" icon="sparkles" heading="Upgrade to the Paid Plan">
            Unlock the full freelancer directory, complete profiles, new projects and consultation booking. Billing will be available soon.
            <x-slot name="actions">
                <flux:button size="sm" variant="primary" disabled>Upgrade to Paid</flux:button>
            </x-slot>
        </flux:callout>
    @endif

    @unless ($this->brandProfileComplete())
        <flux:callout icon="building-office" heading="Complete your brand profile">
            Tell freelancers about your company and pick your interests so we can recommend the right people.
            <x-slot name="actions">
                <flux:button size="sm" href="{{ route('client.brand-profile') }}">Open brand profile</flux:button>
            </x-slot>
        </flux:callout>
    @endunless

    @if ($brand = $this->brand())
        @php($socialLabels = ['linkedin' => 'LinkedIn', 'twitter' => 'X / Twitter', 'instagram' => 'Instagram'])
        @php($socialLinks = collect($brand->social_links ?? [])->filter(fn ($link): bool => filled($link) && preg_match('#^https?://#i', (string) $link) === 1))

        <flux:card class="space-y-4">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="flex min-w-0 items-center gap-4">
                    <flux:avatar size="lg" :src="$brand->logo_path ? Storage::disk('public')->url($brand->logo_path) : null" :name="$brand->company_name" />
                    <div class="min-w-0">
                        <flux:heading size="lg">{{ $brand->company_name }}</flux:heading>
                        @if ($brand->location)
                            <flux:text>{{ $brand->location }}</flux:text>
                        @endif
                    </div>
                </div>

                <flux:button size="sm" icon="pencil-square" href="{{ route('client.brand-profile') }}">Edit brand profile</flux:button>
            </div>

            @if ($brand->description)
                <flux:text class="whitespace-pre-line">{{ $brand->description }}</flux:text>
            @endif

            <dl class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                @if ($brand->contact_email)
                    <div>
                        <dt class="text-zinc-500">Email</dt>
                        <dd class="font-medium">{{ $brand->contact_email }}</dd>
                    </div>
                @endif
                @if ($brand->industry)
                    <div>
                        <dt class="text-zinc-500">Industry</dt>
                        <dd class="font-medium">{{ $brand->industry->name }}</dd>
                    </div>
                @endif
                @if ($brand->professionalFunctions->isNotEmpty())
                    <div class="sm:col-span-2">
                        <dt class="text-zinc-500">Functions</dt>
                        <dd class="mt-1 flex flex-wrap gap-1.5">
                            @foreach ($brand->professionalFunctions as $function)
                                <flux:badge color="blue" size="sm">{{ $function->name }}</flux:badge>
                            @endforeach
                        </dd>
                    </div>
                @endif
                @if ($brand->professionalRoles->isNotEmpty())
                    <div class="sm:col-span-2">
                        <dt class="text-zinc-500">Roles</dt>
                        <dd class="mt-1 flex flex-wrap gap-1.5">
                            @foreach ($brand->professionalRoles as $role)
                                <flux:badge color="indigo" size="sm">{{ $role->name }}</flux:badge>
                            @endforeach
                        </dd>
                    </div>
                @endif
                @if ($socialLinks->isNotEmpty())
                    <div class="sm:col-span-2">
                        <dt class="text-zinc-500">Social links</dt>
                        <dd class="mt-1 flex flex-wrap gap-x-4 gap-y-1">
                            @foreach ($socialLinks as $network => $link)
                                <a href="{{ $link }}" target="_blank" rel="noopener noreferrer nofollow" class="underline">{{ $socialLabels[$network] ?? ucfirst($network) }}</a>
                            @endforeach
                        </dd>
                    </div>
                @endif
            </dl>
        </flux:card>
    @endif

    {{-- The first six calls and projects will be listed here, three to a row, once they exist. --}}
    <section class="space-y-3">
        <div class="flex items-center justify-between gap-3">
            <flux:heading size="lg">Calls</flux:heading>
            <flux:button size="sm" variant="ghost" icon-trailing="arrow-right" href="{{ route('client.calls') }}">View all</flux:button>
        </div>

        <flux:text>You have no upcoming calls.</flux:text>
    </section>

    <section class="space-y-3">
        <div class="flex items-center justify-between gap-3">
            <flux:heading size="lg">Projects</flux:heading>
            <flux:button size="sm" variant="ghost" icon-trailing="arrow-right" href="{{ route('client.projects') }}">View all</flux:button>
        </div>

        <flux:text>You have no projects yet.</flux:text>
    </section>
</div>
