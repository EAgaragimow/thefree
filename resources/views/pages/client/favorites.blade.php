<?php

use App\Actions\Directory\ToggleFavorite;
use App\Models\FreelancerProfile;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Favourites')] class extends Component {
    /**
     * Freelancers the client saved that are still available, most recently saved first. They stay
     * listed after a lapse to the Free plan, but then only as cards that ask to upgrade.
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

<div class="space-y-6">
    @php($favorites = $this->favorites())
    @php($hasFullAccess = Gate::allows('view-full-directory'))
    @php($canFavorite = Gate::allows('favorite-freelancers'))

    <div>
        <flux:heading size="xl" level="1">Favourites</flux:heading>
        <flux:text class="mt-1">Freelancers you saved.</flux:text>
    </div>

    @if ($favorites->isEmpty())
        <flux:callout icon="heart" heading="You have not saved anyone yet">
            Use the heart on a freelancer's card to keep them here.
            <x-slot name="actions">
                <flux:button size="sm" href="{{ route('directory.index') }}">Browse freelancers</flux:button>
            </x-slot>
        </flux:callout>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($favorites as $profile)
                <x-freelancer-card
                    wire:key="favorite-{{ $profile->id }}"
                    :profile="$profile"
                    :href="$hasFullAccess ? route('directory.show', $profile) : null"
                    :locked="! $hasFullAccess"
                    :can-favorite="$canFavorite"
                    :favorited="true"
                />
            @endforeach
        </div>
    @endif

    @unless ($hasFullAccess)
        <x-upgrade-modal />
    @endunless
</div>
