<?php

use App\Actions\Profiles\DiscardProfileRevision;
use App\Actions\Profiles\SaveFreelancerProfile;
use App\Actions\Profiles\SubmitProfileForReview;
use App\Enums\ProfileStatus;
use App\Models\Category;
use App\Models\FreelancerProfile;
use App\Models\Skill;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('My profile')] class extends Component {
    use WithFileUploads;

    #[Locked]
    public int $profileId = 0;

    public string $professional_title = '';

    public string $about = '';

    public string $location = '';

    public string $timezone = 'UTC';

    public string $website = '';

    public string $linkedin = '';

    public string $twitter = '';

    public string $instagram = '';

    /** @var list<int|string> */
    public array $category_ids = [];

    /** @var list<int|string> */
    public array $skill_ids = [];

    /** @var list<array{company: string, role: string, started_at: string, ended_at: string, description: string}> */
    public array $experiences = [];

    public $photo = null;

    public ?string $photo_path = null;

    public ?string $notice = null;

    public function mount(): void
    {
        $profile = auth()->user()->freelancerProfile;

        abort_if($profile === null, 404);

        $this->profileId = $profile->id;
        $this->fillFromContent();
    }

    /**
     * Load the editor from the open revision, or from the live profile when there is none.
     */
    private function fillFromContent(): void
    {
        $content = $this->profile()->workingContent();

        $this->professional_title = (string) $content->get('professional_title');
        $this->about = (string) $content->get('about');
        $this->location = (string) $content->get('location');
        $this->timezone = (string) $content->get('timezone');
        $this->website = (string) $content->get('website');
        $this->linkedin = (string) ($content->get('social_links')['linkedin'] ?? '');
        $this->twitter = (string) ($content->get('social_links')['twitter'] ?? '');
        $this->instagram = (string) ($content->get('social_links')['instagram'] ?? '');
        $this->photo_path = $content->get('photo_path');
        $this->category_ids = array_map('strval', $content->categoryIds());
        $this->skill_ids = array_map('strval', $content->skillIds());
        $this->experiences = array_map(fn (array $experience): array => [
            'company' => $experience['company'],
            'role' => $experience['role'],
            'started_at' => $experience['started_at'],
            'ended_at' => (string) $experience['ended_at'],
            'description' => (string) $experience['description'],
        ], $content->experiences());
    }

    public function profile(): FreelancerProfile
    {
        return FreelancerProfile::query()
            ->with('openRevision')
            ->where('user_id', auth()->id())
            ->findOrFail($this->profileId);
    }

    public function addExperience(): void
    {
        $this->experiences[] = [
            'company' => '',
            'role' => '',
            'started_at' => '',
            'ended_at' => '',
            'description' => '',
        ];
    }

    public function removeExperience(int $index): void
    {
        unset($this->experiences[$index]);
        $this->experiences = array_values($this->experiences);
    }

    public function save(SaveFreelancerProfile $save): void
    {
        $this->persist($save);

        $this->notice = 'Your changes have been saved.';
    }

    public function submitForReview(SaveFreelancerProfile $save, SubmitProfileForReview $submit): void
    {
        $this->persist($save);

        try {
            $submit($this->profile());
        } catch (\DomainException $exception) {
            $this->addError('submit', $exception->getMessage());

            return;
        }

        $this->notice = 'Your profile has been submitted for review.';
    }

    public function discard(DiscardProfileRevision $discard): void
    {
        $this->notice = null;

        try {
            $discard($this->profile());
        } catch (\DomainException $exception) {
            $this->addError('discard', $exception->getMessage());

            return;
        }

        $this->photo = null;
        $this->fillFromContent();
        $this->notice = 'Your changes have been discarded.';
    }

    private function persist(SaveFreelancerProfile $save): void
    {
        $this->notice = null;

        $validated = $this->validate([
            'professional_title' => ['nullable', 'string', 'max:255'],
            'about' => ['nullable', 'string', 'max:5000'],
            'location' => ['nullable', 'string', 'max:255'],
            'timezone' => ['required', 'string', 'timezone:all'],
            'website' => ['nullable', 'url:http,https', 'max:255'],
            'linkedin' => ['nullable', 'url:http,https', 'max:255'],
            'twitter' => ['nullable', 'url:http,https', 'max:255'],
            'instagram' => ['nullable', 'url:http,https', 'max:255'],
            'photo' => ['nullable', 'image', 'max:2048'],
            'category_ids' => ['array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
            'skill_ids' => ['array'],
            'skill_ids.*' => ['integer', 'exists:skills,id'],
            'experiences' => ['array', 'max:20'],
            'experiences.*.company' => ['required', 'string', 'max:255'],
            'experiences.*.role' => ['required', 'string', 'max:255'],
            'experiences.*.started_at' => ['required', 'date'],
            'experiences.*.ended_at' => ['nullable', 'date'],
            'experiences.*.description' => ['nullable', 'string', 'max:2000'],
        ]);

        $profile = $this->profile();

        $photoPath = $this->photo_path;

        if ($this->photo) {
            $photoPath = $this->photo->store('freelancer-photos', 'public');
        }

        $save(
            $profile,
            [
                'photo_path' => $photoPath,
                'professional_title' => $validated['professional_title'] ?: null,
                'about' => $validated['about'] ?: null,
                'location' => $validated['location'] ?: null,
                'timezone' => $validated['timezone'],
                'website' => $validated['website'] ?: null,
                'social_links' => array_filter([
                    'linkedin' => $validated['linkedin'],
                    'twitter' => $validated['twitter'],
                    'instagram' => $validated['instagram'],
                ]),
            ],
            $validated['category_ids'],
            $validated['skill_ids'],
            $validated['experiences'],
        );

        $this->photo = null;
        $this->photo_path = $photoPath;
    }

    public function isLocked(): bool
    {
        return $this->profile()->openRevision?->status === ProfileStatus::PendingReview;
    }

    /**
     * @return \Illuminate\Support\Collection<int, Category>
     */
    public function categories()
    {
        return Category::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
    }

    /**
     * @return \Illuminate\Support\Collection<int, Skill>
     */
    public function skills()
    {
        return Skill::query()->orderBy('name')->get();
    }

    /**
     * @return list<string>
     */
    public function timezones(): array
    {
        return DateTimeZone::listIdentifiers();
    }
};
?>

<div class="mx-auto w-full max-w-3xl">
    <flux:heading size="xl" level="1">My profile</flux:heading>
    <flux:text class="mt-1">Your profile was prepopulated from your application. Complete it and submit it for review.</flux:text>

    @php($profile = $this->profile())
    @php($revision = $profile->openRevision)

    <div class="mt-6 space-y-4">
        @if ($notice)
            <flux:callout variant="success" icon="check-circle" heading="{{ $notice }}" />
        @endif

        @if ($revision?->status === ProfileStatus::ChangesRequested && $revision->last_change_request)
            <flux:callout variant="warning" icon="exclamation-triangle" heading="An administrator requested changes">
                {{ $revision->last_change_request }}
            </flux:callout>
        @endif

        @if ($this->isLocked())
            <flux:callout icon="clock" heading="Your profile is under review">
                You cannot edit it until the review is complete.
            </flux:callout>
        @elseif ($profile->isPublished() && $revision)
            <flux:callout icon="information-circle" heading="Your last approved version is still live">
                Your new changes become visible after an administrator approves them.
            </flux:callout>
        @endif
    </div>

    <form wire:submit="save" class="mt-8 space-y-8">
        <fieldset @disabled($this->isLocked()) class="space-y-8">
            <section class="space-y-4">
                <flux:heading size="lg">Basic details</flux:heading>

                <div class="flex items-center gap-4">
                    <flux:avatar size="lg" :src="$photo_path ? Storage::disk('public')->url($photo_path) : null" :name="auth()->user()->name" />
                    <div>
                        <input type="file" wire:model="photo" accept="image/*" class="text-sm" />
                        @error('photo') <flux:text class="text-red-600">{{ $message }}</flux:text> @enderror
                    </div>
                </div>

                <flux:field>
                    <flux:label>Professional title</flux:label>
                    <flux:input wire:model="professional_title" />
                    <flux:error name="professional_title" />
                </flux:field>

                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:field>
                        <flux:label>Location</flux:label>
                        <flux:input wire:model="location" placeholder="City, Country" />
                        <flux:error name="location" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Time zone</flux:label>
                        <flux:select wire:model="timezone">
                            @foreach ($this->timezones() as $zone)
                                <flux:select.option value="{{ $zone }}">{{ $zone }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="timezone" />
                    </flux:field>
                </div>

                <flux:field>
                    <flux:label>About</flux:label>
                    <flux:textarea wire:model="about" rows="6" />
                    <flux:error name="about" />
                </flux:field>
            </section>

            <section class="space-y-4">
                <flux:heading size="lg">Categories and skills</flux:heading>

                <flux:checkbox.group wire:model="category_ids" label="Categories">
                    @foreach ($this->categories() as $category)
                        <flux:checkbox wire:key="category-{{ $category->id }}" value="{{ $category->id }}" label="{{ $category->name }}" />
                    @endforeach
                </flux:checkbox.group>
                <flux:error name="category_ids" />

                <flux:checkbox.group wire:model="skill_ids" label="Skills">
                    @foreach ($this->skills() as $skill)
                        <flux:checkbox wire:key="skill-{{ $skill->id }}" value="{{ $skill->id }}" label="{{ $skill->name }}" />
                    @endforeach
                </flux:checkbox.group>
                <flux:error name="skill_ids" />
            </section>

            <section class="space-y-4">
                <div class="flex items-center justify-between">
                    <flux:heading size="lg">Work history</flux:heading>
                    <flux:button type="button" size="sm" icon="plus" wire:click="addExperience">Add</flux:button>
                </div>

                @forelse ($experiences as $index => $experience)
                    <flux:card wire:key="experience-{{ $index }}" class="space-y-4">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <flux:field>
                                <flux:label>Company</flux:label>
                                <flux:input wire:model="experiences.{{ $index }}.company" />
                                <flux:error name="experiences.{{ $index }}.company" />
                            </flux:field>

                            <flux:field>
                                <flux:label>Role</flux:label>
                                <flux:input wire:model="experiences.{{ $index }}.role" />
                                <flux:error name="experiences.{{ $index }}.role" />
                            </flux:field>

                            <flux:field>
                                <flux:label>Start date</flux:label>
                                <flux:input type="date" wire:model="experiences.{{ $index }}.started_at" />
                                <flux:error name="experiences.{{ $index }}.started_at" />
                            </flux:field>

                            <flux:field>
                                <flux:label>End date</flux:label>
                                <flux:input type="date" wire:model="experiences.{{ $index }}.ended_at" />
                                <flux:error name="experiences.{{ $index }}.ended_at" />
                            </flux:field>
                        </div>

                        <flux:field>
                            <flux:label>Description</flux:label>
                            <flux:textarea wire:model="experiences.{{ $index }}.description" rows="2" />
                            <flux:error name="experiences.{{ $index }}.description" />
                        </flux:field>

                        <flux:button type="button" size="sm" variant="danger" wire:click="removeExperience({{ $index }})">Remove</flux:button>
                    </flux:card>
                @empty
                    <flux:text>No work history added yet.</flux:text>
                @endforelse
            </section>

            <section class="space-y-4">
                <flux:heading size="lg">Links</flux:heading>

                <flux:field>
                    <flux:label>Website</flux:label>
                    <flux:input type="url" wire:model="website" placeholder="https://" />
                    <flux:error name="website" />
                </flux:field>

                <flux:field>
                    <flux:label>LinkedIn</flux:label>
                    <flux:input type="url" wire:model="linkedin" placeholder="https://" />
                    <flux:error name="linkedin" />
                </flux:field>

                <flux:field>
                    <flux:label>X / Twitter</flux:label>
                    <flux:input type="url" wire:model="twitter" placeholder="https://" />
                    <flux:error name="twitter" />
                </flux:field>

                <flux:field>
                    <flux:label>Instagram</flux:label>
                    <flux:input type="url" wire:model="instagram" placeholder="https://" />
                    <flux:error name="instagram" />
                </flux:field>
            </section>
        </fieldset>

        @unless ($this->isLocked())
            <flux:error name="submit" />
            <flux:error name="discard" />

            <div class="flex flex-wrap gap-3">
                <flux:button type="submit" variant="filled">
                    <span wire:loading.remove wire:target="save">Save draft</span>
                    <span wire:loading wire:target="save">Saving…</span>
                </flux:button>

                <flux:button type="button" variant="primary" wire:click="submitForReview" wire:confirm="Submit your profile for review? You will not be able to edit it until the review is complete.">
                    Submit for review
                </flux:button>

                @if ($profile->isPublished() && $revision)
                    <flux:button type="button" variant="ghost" wire:click="discard" wire:confirm="Discard your changes and go back to the published version of your profile?">
                        Discard changes
                    </flux:button>
                @endif
            </div>
        @endunless
    </form>
</div>
