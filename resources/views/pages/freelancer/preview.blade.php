<?php

use App\Actions\Profiles\ProfileLabels;
use App\Models\FreelancerProfile;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Preview')] class extends Component {
    public function profile(): FreelancerProfile
    {
        $profile = auth()->user()->freelancerProfile;

        abort_if($profile === null, 404);

        return $profile->load('openRevision');
    }
};
?>

<div class="mx-auto w-full max-w-4xl space-y-6">
    @php($profile = $this->profile())
    @php($content = $profile->workingContent())
    @php($labels = app(ProfileLabels::class)($content))

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
        :professional-function="$labels['professionalFunction']"
        :roles="$labels['roles']"
        :skills="$labels['skills']"
        :tools="$labels['tools']"
        :industries="$labels['industries']"
    />
</div>
