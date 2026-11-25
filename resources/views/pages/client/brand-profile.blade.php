<?php

use App\Models\BrandProfile;
use App\Models\Industry;
use App\Models\ProfessionalFunction;
use App\Models\ProfessionalRole;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Brand profile')] class extends Component {
    use WithFileUploads;

    public string $company_name = '';

    public string $description = '';

    public string $industry_id = '';

    public string $website = '';

    public string $location = '';

    public string $linkedin = '';

    public string $twitter = '';

    public string $instagram = '';

    public string $contact_name = '';

    public string $contact_email = '';

    public string $contact_phone = '';

    /** @var list<int|string> */
    public array $function_ids = [];

    /** @var list<int|string> */
    public array $role_ids = [];

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
        $this->industry_id = (string) $profile->industry_id;
        $this->website = (string) $profile->website;
        $this->location = (string) $profile->location;
        $this->linkedin = (string) ($profile->social_links['linkedin'] ?? '');
        $this->twitter = (string) ($profile->social_links['twitter'] ?? '');
        $this->instagram = (string) ($profile->social_links['instagram'] ?? '');
        $this->contact_name = (string) $profile->contact_name;
        $this->contact_email = (string) $profile->contact_email;
        $this->contact_phone = (string) $profile->contact_phone;
        $this->logo_path = $profile->logo_path;
        $this->function_ids = $profile->professionalFunctions->pluck('id')->map(fn ($id) => (string) $id)->all();
        $this->role_ids = $profile->professionalRoles->pluck('id')->map(fn ($id) => (string) $id)->all();
    }

    public function save(): void
    {
        $this->saved = false;

        $validated = $this->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'industry_id' => ['nullable', 'integer', 'exists:industries,id'],
            'website' => ['nullable', 'url', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'linkedin' => ['nullable', 'url', 'max:255'],
            'twitter' => ['nullable', 'url', 'max:255'],
            'instagram' => ['nullable', 'url', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'function_ids' => ['array'],
            'function_ids.*' => ['integer', 'exists:professional_functions,id'],
            'role_ids' => ['array'],
            'role_ids.*' => [
                'integer',
                Rule::exists('professional_roles', 'id')->whereIn('professional_function_id', array_map('intval', $this->function_ids)),
            ],
        ], [
            'role_ids.*.exists' => 'Pick roles that belong to the functions you selected.',
        ]);

        $user = auth()->user();
        $profile = BrandProfile::query()->firstOrNew(['user_id' => $user->id]);

        $profile->fill([
            'company_name' => $validated['company_name'],
            'description' => $validated['description'] ?: null,
            'industry_id' => $validated['industry_id'] ?: null,
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

        $profile->professionalFunctions()->sync($validated['function_ids']);
        $profile->professionalRoles()->sync($validated['role_ids']);

        $this->logo = null;
        $this->logo_path = $profile->logo_path;
        $this->saved = true;
    }

    /**
     * @return list<array{id: string, name: string}>
     */
    public function industryOptions(): array
    {
        return Industry::query()->orderBy('name')->get(['id', 'name'])
            ->map(fn (Industry $industry): array => ['id' => (string) $industry->id, 'name' => $industry->name])
            ->all();
    }

    /**
     * @return list<array{id: string, name: string}>
     */
    public function functionOptions(): array
    {
        return ProfessionalFunction::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name'])
            ->map(fn (ProfessionalFunction $function): array => ['id' => (string) $function->id, 'name' => $function->name])
            ->all();
    }

    /**
     * Drop roles whose function is no longer selected.
     */
    public function updatedFunctionIds(): void
    {
        $this->role_ids = array_values(array_filter(
            $this->role_ids,
            fn ($roleId): bool => in_array((string) $roleId, array_column($this->roleOptions(), 'id'), true),
        ));
    }

    /**
     * Roles of the selected functions only, listed under their function.
     *
     * @return list<array{id: string, name: string, group: string}>
     */
    public function roleOptions(): array
    {
        return ProfessionalRole::selectOptions($this->function_ids);
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
                <flux:label>Company name *</flux:label>
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
                    <flux:label>Location</flux:label>
                    <flux:input wire:model="location" />
                    <flux:error name="location" />
                </flux:field>

                <flux:field>
                    <flux:label>Website</flux:label>
                    <flux:input type="url" wire:model="website" placeholder="https://" />
                    <flux:error name="website" />
                </flux:field>
            </div>
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

            <flux:field>
                <flux:label>Industry</flux:label>
                <flux:select wire:model="industry_id" placeholder="Select your industry">
                    @foreach ($this->industryOptions() as $industry)
                        <flux:select.option wire:key="industry-{{ $industry['id'] }}" value="{{ $industry['id'] }}">{{ $industry['name'] }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="industry_id" />
            </flux:field>

            <x-multi-select wire:model.live="function_ids" label="Functions" :options="$this->functionOptions()" />

            @if ($function_ids === [])
                <flux:text size="sm">Select at least one function to choose roles.</flux:text>
            @else
                <x-multi-select
                    wire:key="brand-roles-{{ implode('-', $function_ids) }}"
                    wire:model="role_ids"
                    label="Roles"
                    description="Roles within the functions you selected."
                    :options="$this->roleOptions()"
                />
            @endif
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

        <flux:button type="submit" variant="primary">
            <span wire:loading.remove wire:target="save">Save brand profile</span>
            <span wire:loading wire:target="save">Saving…</span>
        </flux:button>
    </form>
</div>
