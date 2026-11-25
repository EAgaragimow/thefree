<?php

use App\Actions\Directory\DirectoryPreview;
use App\Actions\Directory\ToggleFavorite;
use App\Enums\SubscriptionType;
use App\Models\BrandProfile;
use App\Models\FreelancerProfile;
use Illuminate\Support\Facades\Gate;
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

    /**
     * How many recommendations the dashboard lists (the directory preview may hold a few more).
     */
    public const RECOMMENDED = 6;

    /**
     * Freelancers that fit the brand profile, from the highest score to the lowest.
     *
     * @return \Illuminate\Support\Collection<int, FreelancerProfile>
     */
    public function recommended(): \Illuminate\Support\Collection
    {
        return app(DirectoryPreview::class)(auth()->user()->brandProfile)
            ->sortByDesc('score')
            ->values()
            ->take(self::RECOMMENDED);
    }

    /**
     * Ids of the freelancers the client saved, to show them as saved on the cards.
     *
     * @return list<int>
     */
    public function favoriteIds(): array
    {
        return auth()->user()->favoriteFreelancers()->pluck('freelancer_profiles.id')->all();
    }

    public function toggleFavorite(int $profileId): void
    {
        $profile = FreelancerProfile::query()->visible()->findOrFail($profileId);

        app(ToggleFavorite::class)(auth()->user(), $profile);
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

    @php($hasFullAccess = Gate::allows('view-full-directory'))
    @php($favoriteIds = $this->favoriteIds())
    @php($canFavorite = Gate::allows('favorite-freelancers'))

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

    <section class="space-y-3">
        <div class="flex items-center justify-between gap-3">
            <flux:heading size="lg">Recommended freelancers</flux:heading>
            <flux:button size="sm" variant="ghost" href="{{ route('directory.index') }}">Browse all</flux:button>
        </div>

        @php($recommended = $this->recommended())

        @if ($recommended->isEmpty())
            <flux:text>There are no published freelancers yet. Please check back soon.</flux:text>
        @else
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($recommended as $profile)
                    <x-freelancer-card
                        wire:key="recommended-{{ $profile->id }}"
                        :profile="$profile"
                        :href="$hasFullAccess ? route('directory.show', $profile) : null"
                        :locked="! $hasFullAccess"
                        :can-favorite="$canFavorite"
                        :favorited="in_array($profile->id, $favoriteIds, true)"
                    />
                @endforeach
            </div>
        @endif
    </section>

    <flux:card>
        <flux:heading>Projects and calls</flux:heading>
        <flux:text class="mt-1">You have no projects or calls yet.</flux:text>
    </flux:card>

    @unless ($hasFullAccess)
        <x-upgrade-modal />
    @endunless
</div>
