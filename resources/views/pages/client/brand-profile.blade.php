<?php

use App\Models\BrandProfile;
use App\Models\Category;
use App\Models\Skill;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Brand profile')] class extends Component {
    use WithFileUploads;

    public string $company_name = '';

    public string $description = '';

    public string $industry = '';

    public string $website = '';

    public string $location = '';

    public string $linkedin = '';

    public string $twitter = '';

    public string $instagram = '';

    public string $contact_name = '';

    public string $contact_email = '';

    public string $contact_phone = '';

    /** @var list<int|string> */
    public array $category_ids = [];

    /** @var list<int|string> */
    public array $skill_ids = [];

    public $logo = null;

    public ?string $logo_path = null;

    public bool $saved = false;

    public function mount(): void
    {
        $profile = auth()->user()->brandProfile;

        if (! $profile) {
            return;
        }

        $this->company_name = $profile->company_name;
        $this->description = (string) $profile->description;
        $this->industry = (string) $profile->industry;
        $this->website = (string) $profile->website;
        $this->location = (string) $profile->location;
        $this->linkedin = (string) ($profile->social_links['linkedin'] ?? '');
        $this->twitter = (string) ($profile->social_links['twitter'] ?? '');
        $this->instagram = (string) ($profile->social_links['instagram'] ?? '');
        $this->contact_name = (string) $profile->contact_name;
        $this->contact_email = (string) $profile->contact_email;
        $this->contact_phone = (string) $profile->contact_phone;
        $this->logo_path = $profile->logo_path;
        $this->category_ids = $profile->categories->pluck('id')->map(fn ($id) => (string) $id)->all();
        $this->skill_ids = $profile->skills->pluck('id')->map(fn ($id) => (string) $id)->all();
    }

    public function save(): void
    {
        $this->saved = false;

        $validated = $this->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'industry' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'linkedin' => ['nullable', 'url', 'max:255'],
            'twitter' => ['nullable', 'url', 'max:255'],
            'instagram' => ['nullable', 'url', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'category_ids' => ['array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
            'skill_ids' => ['array'],
            'skill_ids.*' => ['integer', 'exists:skills,id'],
        ]);

        $user = auth()->user();
        $profile = BrandProfile::query()->firstOrNew(['user_id' => $user->id]);

        $profile->fill([
            'company_name' => $validated['company_name'],
            'description' => $validated['description'] ?: null,
            'industry' => $validated['industry'] ?: null,
            'website' => $validated['website'] ?: null,
            'location' => $validated['location'] ?: null,
            'social_links' => array_filter([
                'linkedin' => $validated['linkedin'],
                'twitter' => $validated['twitter'],
                'instagram' => $validated['instagram'],
            ]),
            'contact_name' => $validated['contact_name'] ?: null,
            'contact_email' => $validated['contact_email'] ?: null,
            'contact_phone' => $validated['contact_phone'] ?: null,
        ]);

        if ($this->logo) {
            if ($profile->logo_path) {
                Storage::disk('public')->delete($profile->logo_path);
            }

            $profile->logo_path = $this->logo->store('brand-logos', 'public');
        }

        $profile->user()->associate($user);
        $profile->save();

        $profile->categories()->sync($validated['category_ids']);
        $profile->skills()->sync($validated['skill_ids']);

        $this->logo = null;
        $this->logo_path = $profile->logo_path;
        $this->saved = true;
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
};
?>

<div class="mx-auto w-full max-w-3xl">
    <flux:heading size="xl" level="1">Brand profile</flux:heading>
    <flux:text class="mt-1">Your brand profile is private. It is only visible to you, administrators and freelancers you interact with.</flux:text>

    <form wire:submit="save" class="mt-8 space-y-8">
        @if ($saved)
            <flux:callout variant="success" icon="check-circle" heading="Your brand profile has been saved." />
        @endif

        <section class="space-y-4">
            <flux:heading size="lg">Company</flux:heading>

            <div class="flex items-center gap-4">
                <flux:avatar size="lg" :src="$logo_path ? Storage::disk('public')->url($logo_path) : null" :name="$company_name" />
                <div>
                    <input type="file" wire:model="logo" accept="image/*" class="text-sm" />
                    @error('logo') <flux:text class="text-red-600">{{ $message }}</flux:text> @enderror
                </div>
            </div>

            <flux:field>
                <flux:label>Company name</flux:label>
                <flux:input wire:model="company_name" required />
                <flux:error name="company_name" />
            </flux:field>

            <flux:field>
                <flux:label>Description</flux:label>
                <flux:textarea wire:model="description" rows="4" />
                <flux:error name="description" />
            </flux:field>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>Industry</flux:label>
                    <flux:input wire:model="industry" />
                    <flux:error name="industry" />
                </flux:field>

                <flux:field>
                    <flux:label>Location</flux:label>
                    <flux:input wire:model="location" />
                    <flux:error name="location" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>Website</flux:label>
                <flux:input type="url" wire:model="website" placeholder="https://" />
                <flux:error name="website" />
            </flux:field>
        </section>

        <section class="space-y-4">
            <flux:heading size="lg">Social links</flux:heading>

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

        <section class="space-y-4">
            <flux:heading size="lg">Contact person</flux:heading>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>Name</flux:label>
                    <flux:input wire:model="contact_name" />
                    <flux:error name="contact_name" />
                </flux:field>

                <flux:field>
                    <flux:label>Phone</flux:label>
                    <flux:input wire:model="contact_phone" />
                    <flux:error name="contact_phone" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>Email</flux:label>
                <flux:input type="email" wire:model="contact_email" />
                <flux:error name="contact_email" />
            </flux:field>
        </section>

        <section class="space-y-4">
            <flux:heading size="lg">Interests</flux:heading>
            <flux:text>We use these to recommend freelancers that fit your needs.</flux:text>

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

        <flux:button type="submit" variant="primary">
            <span wire:loading.remove wire:target="save">Save brand profile</span>
            <span wire:loading wire:target="save">Saving…</span>
        </flux:button>
    </form>
</div>
