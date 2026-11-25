<?php

use App\Models\FreelancerProfile;
use App\Models\Industry;
use App\Models\ProfessionalFunction;
use App\Models\ProfessionalRole;
use App\Models\Skill;
use App\Models\Tool;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Preview')] class extends Component {
    public function profile(): FreelancerProfile
    {
        $profile = auth()->user()->freelancerProfile;

        abort_if($profile === null, 404);

        return $profile->load('openRevision');
    }

    public function functionName(?int $id): ?string
    {
        return $id === null ? null : ProfessionalFunction::query()->whereKey($id)->value('name');
    }

    /**
     * Role names in the order they were picked (main role first).
     *
     * @param  list<int>  $ids
     * @return list<string>
     */
    public function roleNames(array $ids): array
    {
        $names = ProfessionalRole::query()->whereIn('id', $ids)->pluck('name', 'id');

        return collect($ids)->map(fn (int $id): ?string => $names->get($id))->filter()->values()->all();
    }

    /**
     * @param  list<int>  $ids
     * @return list<string>
     */
    public function skillNames(array $ids): array
    {
        return Skill::query()->whereIn('id', $ids)->orderBy('name')->pluck('name')->all();
    }

    /**
     * @param  list<int>  $ids
     * @return list<string>
     */
    public function toolNames(array $ids): array
    {
        return Tool::query()->whereIn('id', $ids)->orderBy('name')->pluck('name')->all();
    }

    /**
     * @param  list<int>  $ids
     * @return list<string>
     */
    public function industryNames(array $ids): array
    {
        return Industry::query()->whereIn('id', $ids)->orderBy('name')->pluck('name')->all();
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
        :professional-function="$this->functionName($content->professionalFunctionId())"
        :roles="$this->roleNames($content->roleIds())"
        :skills="$this->skillNames($content->skillIds())"
        :tools="$this->toolNames($content->toolIds())"
        :industries="$this->industryNames($content->industryIds())"
    />
</div>
