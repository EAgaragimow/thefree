<?php

use App\Actions\Profiles\DiscardProfileRevision;
use App\Actions\Profiles\ProfileContent;
use App\Actions\Profiles\SaveFreelancerProfile;
use App\Actions\Profiles\SubmitProfileForReview;
use App\Enums\ProfileStatus;
use App\Enums\WeeklyHours;
use App\Models\FreelancerProfile;
use App\Models\Industry;
use App\Models\ProfessionalFunction;
use App\Models\ProfessionalRole;
use App\Models\Skill;
use App\Models\Tool;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
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

    public string $professional_function_id = '';

    /** Ordered: the first role is the main role, the second the secondary role, the third the third role. @var list<int|string> */
    public array $role_ids = [];

    /** @var list<int|string> */
    public array $skill_ids = [];

    /** @var list<int|string> */
    public array $tool_ids = [];

    /** @var list<int|string> */
    public array $industry_ids = [];

    public string $hourly_rate = '';

    /** '' (not answered), 'yes' or 'no'. */
    public string $offers_monthly_retainers = '';

    public string $monthly_retainers_description = '';

    public string $monthly_retainers_from = '';

    public string $weekly_hours = '';

    /** '' (not answered), 'yes' or 'no'. */
    public string $open_to_full_time = '';

    public string $best_fit_1 = '';

    public string $best_fit_2 = '';

    public string $best_fit_3 = '';

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
        $this->professional_function_id = (string) $content->professionalFunctionId();
        $this->role_ids = array_map('strval', $content->roleIds());
        $this->skill_ids = array_map('strval', $content->skillIds());
        $this->tool_ids = array_map('strval', $content->toolIds());
        $this->industry_ids = array_map('strval', $content->industryIds());
        $this->hourly_rate = $this->dollars($content->get('hourly_rate_cents'));
        $this->offers_monthly_retainers = $this->yesNo($content->get('offers_monthly_retainers'));
        $this->monthly_retainers_description = (string) $content->get('monthly_retainers_description');
        $this->monthly_retainers_from = $this->dollars($content->get('monthly_retainers_from_cents'));
        $this->weekly_hours = (string) $content->get('weekly_hours');
        $this->open_to_full_time = $this->yesNo($content->get('open_to_full_time'));
        [$this->best_fit_1, $this->best_fit_2, $this->best_fit_3] = array_pad($content->get('best_fit'), 3, '');
    }

    private function dollars(?int $cents): string
    {
        return $cents === null ? '' : number_format($cents / 100, 2, '.', '');
    }

    private function yesNo(?bool $value): string
    {
        return match ($value) {
            true => 'yes',
            false => 'no',
            default => '',
        };
    }

    private function cents(string $dollars): ?int
    {
        return filled($dollars) ? (int) round((float) $dollars * 100) : null;
    }

    public function profile(): FreelancerProfile
    {
        return FreelancerProfile::query()
            ->with('openRevision')
            ->where('user_id', auth()->id())
            ->findOrFail($this->profileId);
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
            'professional_function_id' => ['nullable', 'integer', 'exists:professional_functions,id'],
            'role_ids' => ['array', 'max:'.ProfileContent::MAX_ROLES],
            'role_ids.*' => ['integer', 'distinct', 'exists:professional_roles,id'],
            'skill_ids' => ['array', 'max:'.ProfileContent::MAX_SKILLS],
            'skill_ids.*' => ['integer', 'distinct', 'exists:skills,id'],
            'tool_ids' => ['array', 'max:'.ProfileContent::MAX_TOOLS],
            'tool_ids.*' => ['integer', 'distinct', 'exists:tools,id'],
            'industry_ids' => ['array', 'max:'.ProfileContent::MAX_INDUSTRIES],
            'industry_ids.*' => ['integer', 'distinct', 'exists:industries,id'],
            'hourly_rate' => ['nullable', 'numeric', 'min:1', 'max:100000', 'regex:/^\d+(\.\d{1,2})?$/'],
            'offers_monthly_retainers' => ['nullable', Rule::in(['yes', 'no'])],
            'monthly_retainers_description' => ['nullable', 'string', 'max:2000'],
            'monthly_retainers_from' => ['nullable', 'numeric', 'min:1', 'max:1000000', 'regex:/^\d+(\.\d{1,2})?$/'],
            'weekly_hours' => ['nullable', Rule::enum(WeeklyHours::class)],
            'open_to_full_time' => ['nullable', Rule::in(['yes', 'no'])],
            'best_fit_1' => ['nullable', 'string', 'max:'.ProfileContent::MAX_BEST_FIT_LENGTH],
            'best_fit_2' => ['nullable', 'string', 'max:'.ProfileContent::MAX_BEST_FIT_LENGTH],
            'best_fit_3' => ['nullable', 'string', 'max:'.ProfileContent::MAX_BEST_FIT_LENGTH],
        ], [
            'hourly_rate.regex' => 'Use a rate with at most two decimals, for example 85 or 99.50.',
            'monthly_retainers_from.regex' => 'Use a price with at most two decimals, for example 2500 or 99.50.',
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
                'professional_function_id' => $validated['professional_function_id'] ?: null,
                'role_ids' => $validated['role_ids'],
                'skill_ids' => $validated['skill_ids'],
                'tool_ids' => $validated['tool_ids'],
                'industry_ids' => $validated['industry_ids'],
                'hourly_rate_cents' => $this->cents($validated['hourly_rate'] ?? ''),
                'offers_monthly_retainers' => match ($validated['offers_monthly_retainers'] ?? '') {
                    'yes' => true,
                    'no' => false,
                    default => null,
                },
                'monthly_retainers_description' => $validated['monthly_retainers_description'] ?: null,
                'monthly_retainers_from_cents' => $this->cents($validated['monthly_retainers_from'] ?? ''),
                'weekly_hours' => $validated['weekly_hours'] ?: null,
                'open_to_full_time' => match ($validated['open_to_full_time'] ?? '') {
                    'yes' => true,
                    'no' => false,
                    default => null,
                },
                'best_fit' => [$validated['best_fit_1'] ?? '', $validated['best_fit_2'] ?? '', $validated['best_fit_3'] ?? ''],
            ],
            $profile->workingContent()->experiences(),
        );

        $this->photo = null;
        $this->photo_path = $photoPath;
    }

    public function isLocked(): bool
    {
        return $this->profile()->openRevision?->status === ProfileStatus::PendingReview;
    }

    /**
     * @return \Illuminate\Support\Collection<int, ProfessionalFunction>
     */
    public function functions()
    {
        return ProfessionalFunction::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
    }

    /**
     * Roles listed under the function they belong to.
     *
     * @return list<array{id: string, name: string, group: string}>
     */
    public function roleOptions(): array
    {
        return ProfessionalRole::selectOptions();
    }

    /**
     * @return list<array{id: string, name: string}>
     */
    public function skillOptions(): array
    {
        return $this->options(Skill::query()->orderBy('name'));
    }

    /**
     * @return list<array{id: string, name: string}>
     */
    public function toolOptions(): array
    {
        return $this->options(Tool::query()->orderBy('name'));
    }

    /**
     * @return list<array{id: string, name: string}>
     */
    public function industryOptions(): array
    {
        return $this->options(Industry::query()->orderBy('name'));
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<*>  $query
     * @return list<array{id: string, name: string}>
     */
    private function options($query): array
    {
        return $query->get(['id', 'name'])
            ->map(fn ($item): array => ['id' => (string) $item->id, 'name' => $item->name])
            ->all();
    }

    /**
     * The three technical role fields as they are saved: filled from the roles selection and
     * never edited directly.
     *
     * @return array{main-role: string, secondary-role: string, third-role: string}
     */
    public function technicalRoles(): array
    {
        $roleIds = $this->profile()->workingContent()->roleIds();
        $names = ProfessionalRole::query()->whereIn('id', $roleIds)->pluck('name', 'id');

        return [
            'main-role' => (string) $names->get($roleIds[0] ?? null),
            'secondary-role' => (string) $names->get($roleIds[1] ?? null),
            'third-role' => (string) $names->get($roleIds[2] ?? null),
        ];
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
                        <flux:label>Time zone *</flux:label>
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
                <flux:heading size="lg">Function and roles</flux:heading>

                <flux:field>
                    <flux:label>Function *</flux:label>
                    <flux:description>The one area you work in.</flux:description>
                    <flux:select wire:model="professional_function_id" placeholder="Select your function">
                        @foreach ($this->functions() as $function)
                            <flux:select.option wire:key="function-{{ $function->id }}" value="{{ $function->id }}">{{ $function->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="professional_function_id" />
                </flux:field>

                <x-multi-select
                    wire:model="role_ids"
                    label="Roles"
                    required
                    description="Select up to 3. The order of selection matters: the first role you pick is your main role, then your secondary and third role."
                    :options="$this->roleOptions()"
                    :max="ProfileContent::MAX_ROLES"
                    :min="1"
                    ordered
                    :position-labels="['Main', 'Secondary', 'Third']"
                />
            </section>

            <section class="space-y-6">
                <flux:heading size="lg">Skills, tools and industries</flux:heading>

                <x-multi-select
                    wire:model="skill_ids"
                    label="Skills"
                    required
                    description="Select up to {{ ProfileContent::MAX_SKILLS }} that apply."
                    :options="$this->skillOptions()"
                    :min="ProfileContent::MIN_SKILLS"
                    :max="ProfileContent::MAX_SKILLS"
                />

                <x-multi-select
                    wire:model="tool_ids"
                    label="Tools and platforms"
                    required
                    description="Select up to {{ ProfileContent::MAX_TOOLS }} that apply."
                    :options="$this->toolOptions()"
                    :min="ProfileContent::MIN_TOOLS"
                    :max="ProfileContent::MAX_TOOLS"
                />

                <x-multi-select
                    wire:model="industry_ids"
                    label="Industry experience"
                    description="Select up to {{ ProfileContent::MAX_INDUSTRIES }} that apply."
                    :options="$this->industryOptions()"
                    :max="ProfileContent::MAX_INDUSTRIES"
                />
            </section>

            <section class="space-y-4">
                <flux:heading size="lg">Rates and availability</flux:heading>

                <flux:field>
                    <flux:label>What's your hourly rate? *</flux:label>
                    <flux:description>In US dollars.</flux:description>
                    <flux:input type="number" wire:model="hourly_rate" min="1" step="0.01" inputmode="decimal" icon="currency-dollar" />
                    <flux:error name="hourly_rate" />
                </flux:field>

                <flux:radio.group wire:model.live="offers_monthly_retainers" label="Do you offer monthly retainers? *">
                    <flux:radio value="yes" label="Yes" />
                    <flux:radio value="no" label="No" />
                </flux:radio.group>
                <flux:error name="offers_monthly_retainers" />

                @if ($offers_monthly_retainers === 'yes')
                    <flux:field>
                        <flux:label>Describe the scope of services your monthly retainer offers *</flux:label>
                        <flux:textarea wire:model="monthly_retainers_description" rows="3" />
                        <flux:error name="monthly_retainers_description" />
                    </flux:field>

                    <flux:field>
                        <flux:label>What do your retainers start at? *</flux:label>
                        <flux:description>You can customize retainers per client; this is just your starting price, in US dollars per month.</flux:description>
                        <flux:input type="number" wire:model="monthly_retainers_from" min="1" step="0.01" inputmode="decimal" icon="currency-dollar" />
                        <flux:error name="monthly_retainers_from" />
                    </flux:field>
                @endif

                <flux:field>
                    <flux:label>How many hours per week are you available? *</flux:label>
                    <flux:select wire:model="weekly_hours" placeholder="Select your availability">
                        @foreach (WeeklyHours::cases() as $hours)
                            <flux:select.option value="{{ $hours->value }}">{{ $hours->value }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="weekly_hours" />
                </flux:field>

                <flux:radio.group wire:model="open_to_full_time" label="Are you open to full-time opportunities? *">
                    <flux:radio value="yes" label="Yes" />
                    <flux:radio value="no" label="No" />
                </flux:radio.group>
                <flux:error name="open_to_full_time" />
            </section>

            <section class="space-y-4">
                <div>
                    <flux:heading size="lg">What types of brands are you a good fit for?</flux:heading>
                    <flux:text>Describe the kinds of companies or growth problems you're the best fit for.</flux:text>
                </div>

                <flux:field>
                    <flux:label class="sr-only">Best fit 1</flux:label>
                    <flux:input wire:model="best_fit_1" placeholder="Ex: Brands spending at least $500K/mo" />
                    <flux:error name="best_fit_1" />
                </flux:field>

                <flux:field>
                    <flux:label class="sr-only">Best fit 2</flux:label>
                    <flux:input wire:model="best_fit_2" placeholder="Ex: Subscription-based businesses" />
                    <flux:error name="best_fit_2" />
                </flux:field>

                <flux:field>
                    <flux:label class="sr-only">Best fit 3</flux:label>
                    <flux:input wire:model="best_fit_3" placeholder="Ex: Companies in the personal care industry" />
                    <flux:error name="best_fit_3" />
                </flux:field>
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

        <section class="space-y-4" aria-labelledby="hidden-technical-fields">
            <div>
                <flux:heading id="hidden-technical-fields" size="lg">Hidden Technical Fields</flux:heading>
                <flux:text>These are filled in automatically from your roles when you save. They cannot be edited.</flux:text>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                @foreach ($this->technicalRoles() as $name => $roleName)
                    <flux:field wire:key="technical-{{ $name }}">
                        <flux:label>{{ $name }}</flux:label>
                        <flux:input value="{{ $roleName }}" readonly placeholder="—" />
                    </flux:field>
                @endforeach
            </div>
        </section>

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
