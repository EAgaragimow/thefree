<?php

use App\Actions\Directory\DirectoryPreview;
use App\Actions\Directory\ToggleFavorite;
use App\Enums\SubscriptionType;
use App\Models\FreelancerProfile;
use Illuminate\Support\Facades\Gate;
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

    public function isFreePlan(): bool
    {
        return auth()->user()->subscription_type === SubscriptionType::FreePlan;
    }

    /**
     * How many recommendations the dashboard lists (the directory preview may hold a few more).
     */
    public const RECOMMENDED = 6;

    /**
     * Freelancers that fit the brand profile, best first.
     *
     * @return \Illuminate\Support\Collection<int, FreelancerProfile>
     */
    public function recommended(): \Illuminate\Support\Collection
    {
        return app(DirectoryPreview::class)(auth()->user()->brandProfile)->take(self::RECOMMENDED);
    }

    /**
     * Freelancers the client saved that are still available. They are listed even after a lapse
     * to the Free plan, but then only as cards that ask to upgrade.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, FreelancerProfile>
     */
    public function favorites(): \Illuminate\Database\Eloquent\Collection
    {
        return auth()->user()->favoriteFreelancers()->visible()->forCard()->get();
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
    @php($favorites = $this->favorites())
    @php($favoriteIds = $favorites->modelKeys())
    @php($canFavorite = Gate::allows('favorite-freelancers'))

    @if ($favorites->isNotEmpty())
        <section class="space-y-3">
            <flux:heading size="lg">Favorites</flux:heading>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($favorites as $profile)
                    <x-freelancer-card
                        wire:key="favorite-card-{{ $profile->id }}"
                        :profile="$profile"
                        :href="$hasFullAccess ? route('directory.show', $profile) : null"
                        :locked="! $hasFullAccess"
                        :can-favorite="$canFavorite"
                        :favorited="true"
                    />
                @endforeach
            </div>
        </section>
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
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
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
