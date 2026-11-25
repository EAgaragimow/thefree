<?php

use App\Enums\ApplicationStatus;
use App\Actions\Profiles\ProfileContent;
use App\Models\FreelancerApplication;
use App\Models\ProfessionalRole;
use App\Models\Skill;
use App\Models\User;
use App\Support\Locations;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('components.layouts.public'), Title('Become a Freelancer')] class extends Component {
    public string $full_name = '';

    public string $email = '';

    public string $phone = '';

    public string $country = '';

    public string $state = '';

    public string $city = '';

    /** Ordered: the first role is the main role. @var list<int|string> */
    public array $role_ids = [];

    public ?int $years_experience = null;

    /** @var list<int|string> */
    public array $skill_ids = [];

    public string $about = '';

    public string $experience_summary = '';

    public string $portfolio_links = '';

    public string $linkedin = '';

    public string $twitter = '';

    public string $instagram = '';

    /** Honeypot: real users never fill this in. */
    public string $company_website = '';

    public bool $submitted = false;

    public function submit(): void
    {
        if ($this->company_website !== '') {
            $this->submitted = true;

            return;
        }

        $throttleKey = 'freelancer-application:'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'email' => 'Too many applications from this connection. Please try again later.',
            ]);
        }

        $validated = $this->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique(User::class, 'email'),
                Rule::unique(FreelancerApplication::class, 'email')->where(
                    fn ($query) => $query->whereIn('status', [ApplicationStatus::Pending->value, ApplicationStatus::Approved->value])
                ),
            ],
            'phone' => ['nullable', 'string', 'max:50'],
            'country' => ['required', Rule::in(Locations::countries())],
            'state' => [Rule::requiredIf($this->hasStates()), 'nullable', Rule::in(Locations::states($this->country))],
            'city' => [Rule::requiredIf($this->hasCities()), 'nullable', Rule::in(Locations::cities($this->country, $this->state))],
            'role_ids' => ['required', 'array', 'min:1', 'max:'.ProfileContent::MAX_ROLES],
            'role_ids.*' => ['integer', 'distinct', 'exists:professional_roles,id'],
            'years_experience' => ['nullable', 'integer', 'min:0', 'max:60'],
            'skill_ids' => ['required', 'array', 'min:1', 'max:'.ProfileContent::MAX_SKILLS],
            'skill_ids.*' => ['integer', 'distinct', 'exists:skills,id'],
            'about' => ['nullable', 'string', 'max:5000'],
            'experience_summary' => ['required', 'string', 'min:50', 'max:5000'],
            'portfolio_links' => ['required', 'string', 'max:2000'],
            'linkedin' => ['nullable', 'url', 'max:255'],
            'twitter' => ['nullable', 'url', 'max:255'],
            'instagram' => ['nullable', 'url', 'max:255'],
        ], [
            'email.unique' => 'An application or account with this email address already exists.',
        ]);

        $links = collect(preg_split('/\R+/', $validated['portfolio_links']))
            ->map(fn ($link) => trim($link))
            ->filter()
            ->values();

        foreach ($links as $link) {
            if (! filter_var($link, FILTER_VALIDATE_URL)) {
                throw ValidationException::withMessages([
                    'portfolio_links' => "\"{$link}\" is not a valid URL. Put one link per line.",
                ]);
            }
        }

        $roleNames = ProfessionalRole::query()->whereIn('id', $validated['role_ids'])->pluck('name', 'id');
        $roleNames = collect($validated['role_ids'])->map(fn ($id): string => $roleNames[(int) $id])->values();

        $state = $this->hasStates() ? $validated['state'] : null;
        $city = $this->hasCities() ? $validated['city'] : null;

        FreelancerApplication::query()->create([
            'email' => mb_strtolower($validated['email']),
            'full_name' => $validated['full_name'],
            'phone' => $validated['phone'] ?: null,
            'country' => $validated['country'],
            'state' => $state,
            'city' => $city,
            'location' => collect([$city, $state, $validated['country']])->filter()->implode(', '),
            'specialty' => $roleNames->first(),
            'roles' => $roleNames->all(),
            'years_experience' => $validated['years_experience'],
            'experience_summary' => $validated['experience_summary'],
            'about' => $validated['about'] ?: null,
            'skills' => Skill::query()->whereIn('id', $validated['skill_ids'])->orderBy('name')->pluck('name')->all(),
            'portfolio_links' => $links->take(10)->all(),
            'social_links' => array_filter([
                'linkedin' => $validated['linkedin'],
                'twitter' => $validated['twitter'],
                'instagram' => $validated['instagram'],
            ]),
        ]);

        RateLimiter::hit($throttleKey, 3600);

        $this->submitted = true;
    }

    /**
     * Choosing another country clears the state/province and city; choosing another state clears the city.
     */
    public function updatedCountry(): void
    {
        $this->state = '';
        $this->city = '';
        $this->resetValidation(['state', 'city']);
    }

    public function updatedState(): void
    {
        $this->city = '';
        $this->resetValidation('city');
    }

    /**
     * Whether the chosen country has states/provinces to pick from. Some countries have none.
     */
    public function hasStates(): bool
    {
        return Locations::states($this->country) !== [];
    }

    /**
     * Whether the chosen state/province has cities to pick from. Some have none.
     */
    public function hasCities(): bool
    {
        return Locations::cities($this->country, $this->state) !== [];
    }

    /**
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
        return Skill::query()->orderBy('name')->get(['id', 'name'])
            ->map(fn (Skill $skill): array => ['id' => (string) $skill->id, 'name' => $skill->name])
            ->all();
    }
};
?>

<div class="mx-auto w-full max-w-2xl py-8">
    @if ($submitted)
        <flux:callout variant="success" icon="check-circle" heading="Application received">
            Thank you for applying! Our team will review your application and email you with the decision.
        </flux:callout>
    @else
        <flux:heading size="xl" level="1">Become a Freelancer</flux:heading>
        <flux:text class="mt-1">Tell us about yourself. Applications are reviewed by our team; approved applicants receive an invitation to activate their account.</flux:text>

        <form wire:submit="submit" class="mt-8 space-y-8">
            <div class="hidden" aria-hidden="true">
                <label>Leave this field empty <input type="text" wire:model="company_website" tabindex="-1" autocomplete="off"></label>
            </div>

            <section class="space-y-4">
                <flux:heading size="lg">About you</flux:heading>

                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:field>
                        <flux:label>Full name *</flux:label>
                        <flux:input wire:model="full_name" required autocomplete="name" />
                        <flux:error name="full_name" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Email *</flux:label>
                        <flux:input type="email" wire:model="email" required autocomplete="email" />
                        <flux:error name="email" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Phone</flux:label>
                        <flux:input wire:model="phone" autocomplete="tel" />
                        <flux:error name="phone" />
                    </flux:field>

                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <flux:field>
                        <flux:label>Country *</flux:label>
                        <flux:select wire:model.live="country" placeholder="Select a country" required>
                            @foreach (Locations::countries() as $name)
                                <flux:select.option wire:key="country-{{ $loop->index }}" value="{{ $name }}">{{ $name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="country" />
                    </flux:field>

                    <flux:field wire:key="state-{{ $country }}">
                        <flux:label>State/Province @if ($country === '' || $this->hasStates())*@endif</flux:label>
                        <flux:select
                            wire:model.live="state"
                            :required="$country === '' || $this->hasStates()"
                            :disabled="! $this->hasStates()"
                            :placeholder="$country === '' ? 'Select a country first' : ($this->hasStates() ? 'Select a state/province' : 'Not applicable')"
                        >
                            @foreach (Locations::states($country) as $name)
                                <flux:select.option wire:key="state-{{ $loop->index }}" value="{{ $name }}">{{ $name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="state" />
                    </flux:field>

                    <flux:field wire:key="city-{{ $country }}-{{ $state }}">
                        <flux:label>City @if ($this->hasCities())*@endif</flux:label>
                        <flux:select
                            wire:model="city"
                            :required="$this->hasCities()"
                            :disabled="! $this->hasCities()"
                            :placeholder="$state === '' ? ($country !== '' && ! $this->hasStates() ? 'Not applicable' : 'Select a state/province first') : ($this->hasCities() ? 'Select a city' : 'Not applicable')"
                        >
                            @foreach (Locations::cities($country, $state) as $name)
                                <flux:select.option wire:key="city-{{ $loop->index }}" value="{{ $name }}">{{ $name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="city" />
                    </flux:field>
                </div>

                <flux:field>
                    <flux:label>Short bio</flux:label>
                    <flux:textarea wire:model="about" rows="3" />
                    <flux:error name="about" />
                </flux:field>
            </section>

            <section class="space-y-4">
                <flux:heading size="lg">Professional details</flux:heading>

                <x-multi-select
                    wire:model="role_ids"
                    label="Roles"
                    required
                    description="Select up to {{ ProfileContent::MAX_ROLES }}. The order of selection matters: the first role you pick is your main role, then your secondary and third role."
                    :options="$this->roleOptions()"
                    :max="ProfileContent::MAX_ROLES"
                    :min="1"
                    ordered
                    :position-labels="['Main', 'Secondary', 'Third']"
                />

                <flux:field class="sm:max-w-xs">
                    <flux:label>Years of experience</flux:label>
                    <flux:input type="number" min="0" wire:model="years_experience" />
                    <flux:error name="years_experience" />
                </flux:field>

                <x-multi-select
                    wire:model="skill_ids"
                    label="Skills"
                    required
                    description="Select up to {{ ProfileContent::MAX_SKILLS }} that apply."
                    :options="$this->skillOptions()"
                    :max="ProfileContent::MAX_SKILLS"
                    :min="1"
                />

                <flux:field>
                    <flux:label>Experience summary *</flux:label>
                    <flux:textarea wire:model="experience_summary" rows="5" required />
                    <flux:description>At least 50 characters. Tell us about your background and notable work.</flux:description>
                    <flux:error name="experience_summary" />
                </flux:field>
            </section>

            <section class="space-y-4">
                <flux:heading size="lg">Portfolio and links</flux:heading>

                <flux:field>
                    <flux:label>Portfolio links *</flux:label>
                    <flux:textarea wire:model="portfolio_links" rows="3" required placeholder="One link per line" />
                    <flux:error name="portfolio_links" />
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

            <flux:button type="submit" variant="primary">
                <span wire:loading.remove wire:target="submit">Submit application</span>
                <span wire:loading wire:target="submit">Submitting…</span>
            </flux:button>
        </form>
    @endif
</div>
