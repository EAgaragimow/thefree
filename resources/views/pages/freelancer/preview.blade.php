<?php

use App\Models\Category;
use App\Models\FreelancerProfile;
use App\Models\Skill;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Preview')] class extends Component {
    public function profile(): FreelancerProfile
    {
        $profile = auth()->user()->freelancerProfile;

        abort_if($profile === null, 404);

        return $profile->load('openRevision');
    }

    /**
     * @param  list<int>  $ids
     * @return list<string>
     */
    public function categoryNames(array $ids): array
    {
        return Category::query()->whereIn('id', $ids)->orderBy('name')->pluck('name')->all();
    }

    /**
     * @param  list<int>  $ids
     * @return list<string>
     */
    public function skillNames(array $ids): array
    {
        return Skill::query()->whereIn('id', $ids)->orderBy('name')->pluck('name')->all();
    }
};
?>

<div class="mx-auto w-full max-w-4xl space-y-6">
    @php($profile = $this->profile())
    @php($content = $profile->workingContent())

    <flux:callout icon="eye" heading="Preview">
        This is how clients will see your profile, including changes that are not published yet.
        @if ($profile->openRevision)
            Your pending changes are included.
        @endif
        <x-slot name="actions">
            <flux:button size="sm" href="{{ route('freelancer.profile') }}">Back to editing</flux:button>
        </x-slot>
    </flux:callout>

    <x-freelancer-profile
        :content="$content"
        :user="auth()->user()"
        :categories="$this->categoryNames($content->categoryIds())"
        :skills="$this->skillNames($content->skillIds())"
    />
</div>
