<?php

use App\Actions\Directory\DirectoryPreview;
use App\Actions\Directory\ToggleFavorite;
use App\Models\FreelancerProfile;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Recommended freelancers')] class extends Component {
    /**
     * Every freelancer that fits the brand profile, from the highest score to the lowest. The
     * dashboard lists the first few of them.
     *
     * @return \Illuminate\Support\Collection<int, FreelancerProfile>
     */
    public function recommended(): \Illuminate\Support\Collection
    {
        return app(DirectoryPreview::class)(auth()->user()->brandProfile)
            ->sortByDesc('score')
            ->values();
    }

    /**
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

<div class="space-y-6">
    @php($recommended = $this->recommended())
    @php($favoriteIds = $this->favoriteIds())
    @php($hasFullAccess = Gate::allows('view-full-directory'))
    @php($canFavorite = Gate::allows('favorite-freelancers'))

    <div>
        <flux:heading size="xl" level="1">Recommended freelancers</flux:heading>
        <flux:text class="mt-1">Freelancers that fit your brand profile, from the highest score to the lowest.</flux:text>
    </div>

    @if ($recommended->isEmpty())
        <flux:callout icon="users" heading="No freelancers to recommend yet">
            There are no published freelancers yet. Please check back soon.
        </flux:callout>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($recommended as $profile)
                <x-freelancer-card
                    wire:key="recommended-{{ $profile->id }}"
                    :profile="$profile"
                    :href="$hasFullAccess ? route('directory.show', $profile->slug) : null"
                    :locked="! $hasFullAccess"
                    :can-favorite="$canFavorite"
                    :favorited="in_array($profile->id, $favoriteIds, true)"
                />
            @endforeach
        </div>
    @endif

    @unless ($hasFullAccess)
        <x-upgrade-modal />
    @endunless
</div>
