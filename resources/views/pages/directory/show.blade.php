<?php

use App\Actions\Directory\ToggleFavorite;
use App\Actions\Profiles\ProfileLabels;
use App\Models\FreelancerProfile;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Freelancer profile')] class extends Component {
    public int $profileId;

    /**
     * Full profiles are for Paid clients, freelancers and admins; a Free client is turned away
     * here even if they know the address. Profiles that are not live do not exist for anyone.
     */
    public function mount(int $profile): void
    {
        Gate::authorize('view-full-freelancer-profile');

        $this->profileId = FreelancerProfile::query()->visible()->findOrFail($profile)->id;
    }

    public function profile(): FreelancerProfile
    {
        return FreelancerProfile::query()->visible()->with('user')->findOrFail($this->profileId);
    }

    public function toggleFavorite(int $profileId): void
    {
        $profile = FreelancerProfile::query()->visible()->findOrFail($profileId);

        app(ToggleFavorite::class)(auth()->user(), $profile);
    }

    public function isFavorite(): bool
    {
        return auth()->user()->favoriteFreelancers()->whereKey($this->profileId)->exists();
    }
};
?>

<div class="mx-auto w-full max-w-4xl space-y-6">
    @php($profile = $this->profile())
    @php($content = $profile->liveContent())
    @php($labels = app(ProfileLabels::class)($content))

    <div class="flex flex-wrap items-center justify-between gap-3">
        <flux:button size="sm" variant="ghost" icon="arrow-left" href="{{ route('directory.index') }}">All freelancers</flux:button>

        @can('favorite-freelancers')
            <flux:button size="sm" wire:click="toggleFavorite({{ $profile->id }})" :icon="$this->isFavorite() ? 'check' : 'heart'">
                {{ $this->isFavorite() ? 'Saved to favorites' : 'Save to favorites' }}
            </flux:button>
        @endcan
    </div>

    <x-freelancer-profile
        :content="$content"
        :user="$profile->user"
        :professional-function="$labels['professionalFunction']"
        :roles="$labels['roles']"
        :skills="$labels['skills']"
        :tools="$labels['tools']"
        :industries="$labels['industries']"
    />
</div>
