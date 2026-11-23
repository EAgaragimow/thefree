<?php

use App\Enums\ApplicationStatus;
use App\Models\FreelancerApplication;
use App\Models\User;
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

    public string $location = '';

    public string $specialty = '';

    public ?int $years_experience = null;

    public string $skills = '';

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
            'location' => ['required', 'string', 'max:255'],
            'specialty' => ['required', 'string', 'max:255'],
            'years_experience' => ['nullable', 'integer', 'min:0', 'max:60'],
            'skills' => ['required', 'string', 'max:1000'],
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

        FreelancerApplication::query()->create([
            'email' => mb_strtolower($validated['email']),
            'full_name' => $validated['full_name'],
            'phone' => $validated['phone'] ?: null,
            'location' => $validated['location'],
            'specialty' => $validated['specialty'],
            'years_experience' => $validated['years_experience'],
            'experience_summary' => $validated['experience_summary'],
            'about' => $validated['about'] ?: null,
            'skills' => collect(explode(',', $validated['skills']))->map(fn ($skill) => trim($skill))->filter()->unique()->take(20)->values()->all(),
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
                        <flux:label>Full name</flux:label>
                        <flux:input wire:model="full_name" required autocomplete="name" />
                        <flux:error name="full_name" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Email</flux:label>
                        <flux:input type="email" wire:model="email" required autocomplete="email" />
                        <flux:error name="email" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Phone (optional)</flux:label>
                        <flux:input wire:model="phone" autocomplete="tel" />
                        <flux:error name="phone" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Location</flux:label>
                        <flux:input wire:model="location" required placeholder="City, Country" />
                        <flux:error name="location" />
                    </flux:field>
                </div>

                <flux:field>
                    <flux:label>Short bio (optional)</flux:label>
                    <flux:textarea wire:model="about" rows="3" />
                    <flux:error name="about" />
                </flux:field>
            </section>

            <section class="space-y-4">
                <flux:heading size="lg">Professional details</flux:heading>

                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:field>
                        <flux:label>Specialty</flux:label>
                        <flux:input wire:model="specialty" required placeholder="e.g. Brand designer" />
                        <flux:error name="specialty" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Years of experience</flux:label>
                        <flux:input type="number" min="0" wire:model="years_experience" />
                        <flux:error name="years_experience" />
                    </flux:field>
                </div>

                <flux:field>
                    <flux:label>Skills</flux:label>
                    <flux:input wire:model="skills" required placeholder="Comma separated, e.g. Figma, Branding, Illustration" />
                    <flux:error name="skills" />
                </flux:field>

                <flux:field>
                    <flux:label>Experience summary</flux:label>
                    <flux:textarea wire:model="experience_summary" rows="5" required />
                    <flux:description>At least 50 characters. Tell us about your background and notable work.</flux:description>
                    <flux:error name="experience_summary" />
                </flux:field>
            </section>

            <section class="space-y-4">
                <flux:heading size="lg">Portfolio and links</flux:heading>

                <flux:field>
                    <flux:label>Portfolio links</flux:label>
                    <flux:textarea wire:model="portfolio_links" rows="3" required placeholder="One link per line" />
                    <flux:error name="portfolio_links" />
                </flux:field>

                <flux:field>
                    <flux:label>LinkedIn (optional)</flux:label>
                    <flux:input type="url" wire:model="linkedin" placeholder="https://" />
                    <flux:error name="linkedin" />
                </flux:field>

                <flux:field>
                    <flux:label>X / Twitter (optional)</flux:label>
                    <flux:input type="url" wire:model="twitter" placeholder="https://" />
                    <flux:error name="twitter" />
                </flux:field>

                <flux:field>
                    <flux:label>Instagram (optional)</flux:label>
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
