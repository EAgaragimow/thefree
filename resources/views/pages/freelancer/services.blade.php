<?php

use App\Actions\Profiles\ProfileContent;
use App\Actions\Profiles\UpdateProfileContent;
use App\Enums\PricingMode;
use App\Enums\ProfileStatus;
use App\Models\FreelancerProfile;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Services')] class extends Component {
    #[Locked]
    public int $profileId = 0;

    /** The uid of the service being edited, or null when adding a new one. */
    public ?string $editingUid = null;

    public string $title = '';

    public string $description = '';

    public string $pricing_mode = 'fixed';

    public string $price = '';

    public function mount(): void
    {
        $profile = auth()->user()->freelancerProfile;

        abort_if($profile === null, 404);

        $this->profileId = $profile->id;
    }

    public function profile(): FreelancerProfile
    {
        return FreelancerProfile::query()
            ->with('openRevision')
            ->where('user_id', auth()->id())
            ->findOrFail($this->profileId);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function services(): array
    {
        return $this->profile()->workingContent()->services();
    }

    public function isLocked(): bool
    {
        return $this->profile()->openRevision?->status === ProfileStatus::PendingReview;
    }

    #[On('profile-revision-changed')]
    public function resetForm(): void
    {
        $this->reset('editingUid', 'title', 'description', 'pricing_mode', 'price');
        $this->resetValidation();
    }

    public function startCreating(): void
    {
        $this->resetForm();

        Flux::modal('service-form')->show();
    }

    public function startEditing(string $uid): void
    {
        $service = collect($this->services())->firstWhere('uid', $uid);

        abort_if($service === null, 404);

        $this->resetForm();
        $this->editingUid = $uid;
        $this->title = $service['title'];
        $this->description = (string) $service['description'];
        $this->pricing_mode = $service['pricing_mode'];
        $this->price = $service['price_cents'] === null ? '' : number_format($service['price_cents'] / 100, 2, '.', '');

        Flux::modal('service-form')->show();
    }

    public function save(UpdateProfileContent $update): void
    {
        $this->validate([
            'title' => ['required', 'string', 'max:'.ProfileContent::MAX_SERVICE_TITLE],
            'description' => ['nullable', 'string', 'max:'.ProfileContent::MAX_SERVICE_DESCRIPTION],
            'pricing_mode' => ['required', Rule::enum(PricingMode::class)],
            'price' => [
                Rule::requiredIf(fn (): bool => PricingMode::tryFrom($this->pricing_mode)?->requiresPrice() ?? false),
                'nullable',
                'numeric',
                'min:1',
                'max:1000000',
                'regex:/^\d+(\.\d{1,2})?$/',
            ],
        ], [
            'price.required' => 'Enter a price, or choose "Custom quote" if the price depends on the request.',
            'price.regex' => 'Use a price with at most two decimals, for example 1500 or 99.50.',
        ]);

        $services = $this->services();

        if ($this->editingUid === null && count($services) >= ProfileContent::MAX_SERVICES) {
            $this->addError('title', 'You can list at most '.ProfileContent::MAX_SERVICES.' services.');

            return;
        }

        $mode = PricingMode::from($this->pricing_mode);

        $values = [
            'title' => trim($this->title),
            'description' => $this->description,
            'pricing_mode' => $mode->value,
            'price_cents' => $mode->requiresPrice() ? (int) round((float) $this->price * 100) : null,
        ];

        $found = false;

        $services = array_map(function (array $service) use ($values, &$found): array {
            if ($service['uid'] !== $this->editingUid) {
                return $service;
            }

            $found = true;

            return [...$service, ...$values];
        }, $services);

        if ($this->editingUid !== null && ! $found) {
            abort(404);
        }

        if ($this->editingUid === null) {
            $services[] = $values;
        }

        if (! $this->persist($update, $services)) {
            return;
        }

        Flux::modal('service-form')->close();
        $this->resetForm();
    }

    public function delete(string $uid, UpdateProfileContent $update): void
    {
        $this->persist($update, array_values(array_filter(
            $this->services(),
            fn (array $service): bool => $service['uid'] !== $uid,
        )));
    }

    public function sort(string $uid, int $position, UpdateProfileContent $update): void
    {
        $services = $this->services();
        $moved = collect($services)->firstWhere('uid', $uid);

        abort_if($moved === null, 404);

        $others = array_values(array_filter($services, fn (array $service): bool => $service['uid'] !== $uid));
        array_splice($others, max(0, min($position, count($others))), 0, [$moved]);

        $this->persist($update, $others);
    }

    /**
     * @param  list<array<string, mixed>>  $services
     */
    private function persist(UpdateProfileContent $update, array $services): bool
    {
        try {
            $update($this->profile(), ['services' => $services]);
        } catch (\DomainException $exception) {
            $this->addError('title', $exception->getMessage());

            return false;
        }

        $this->dispatch('profile-content-saved');

        return true;
    }

    public function priceLabel(array $service): string
    {
        $amount = '$'.number_format(($service['price_cents'] ?? 0) / 100, 2);

        return match (PricingMode::from($service['pricing_mode'])) {
            PricingMode::Fixed => $amount,
            PricingMode::StartingFrom => "From {$amount}",
            PricingMode::CustomQuote => 'Custom quote',
        };
    }
};
?>

<div class="mx-auto w-full max-w-3xl space-y-6">
    <div class="flex items-start justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">Services</flux:heading>
            <flux:text class="mt-1">Describe the work you offer, up to {{ ProfileContent::MAX_SERVICES }} services. Clients never buy a service directly: they send you a request, and you agree on scope, price and deadline together.</flux:text>
        </div>

        @unless ($this->isLocked() || count($this->services()) >= ProfileContent::MAX_SERVICES)
            <flux:button variant="primary" icon="plus" wire:click="startCreating">Add service</flux:button>
        @endunless
    </div>

    <livewire:profile-revision-bar />

    @php($services = $this->services())

    @if ($services === [])
        <flux:card>
            <flux:heading>No services yet</flux:heading>
            <flux:text class="mt-1">Add the services you want clients to request from you.</flux:text>
        </flux:card>
    @else
        <ul wire:sort="sort" class="space-y-3">
            @foreach ($services as $service)
                <li wire:key="service-{{ $service['uid'] }}" wire:sort:item="{{ $service['uid'] }}">
                    <flux:card class="flex items-start gap-4">
                        <div wire:sort:handle class="mt-1 cursor-grab text-zinc-400" title="Drag to reorder" aria-hidden="true">
                            <flux:icon.bars-3 class="size-5" />
                        </div>

                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <flux:heading>{{ $service['title'] }}</flux:heading>
                                <flux:badge size="sm">{{ $this->priceLabel($service) }}</flux:badge>
                            </div>

                            @if ($service['description'])
                                <flux:text class="mt-1 whitespace-pre-line">{{ $service['description'] }}</flux:text>
                            @endif
                        </div>

                        @unless ($this->isLocked())
                            <div class="flex gap-2" wire:sort:ignore>
                                <flux:button size="sm" wire:click="startEditing('{{ $service['uid'] }}')">Edit</flux:button>
                                <flux:button size="sm" variant="danger" wire:click="delete('{{ $service['uid'] }}')" wire:confirm="Remove this service?">Remove</flux:button>
                            </div>
                        @endunless
                    </flux:card>
                </li>
            @endforeach
        </ul>
    @endif

    <flux:modal name="service-form" class="md:w-[34rem]">
        <form wire:submit="save" class="space-y-5">
            <flux:heading size="lg">{{ $editingUid === null ? 'Add service' : 'Edit service' }}</flux:heading>

            <flux:field>
                <flux:label>Title *</flux:label>
                <flux:input wire:model="title" maxlength="{{ ProfileContent::MAX_SERVICE_TITLE }}" placeholder="Brand identity design" />
                <flux:error name="title" />
            </flux:field>

            <flux:field>
                <flux:label>Description</flux:label>
                <flux:description>Up to {{ ProfileContent::MAX_SERVICE_DESCRIPTION }} characters.</flux:description>
                <flux:textarea wire:model="description" rows="4" maxlength="{{ ProfileContent::MAX_SERVICE_DESCRIPTION }}" placeholder="What is included, how you work, what you need from the client." />
                <flux:error name="description" />
            </flux:field>

            <flux:radio.group wire:model.live="pricing_mode" label="Pricing *">
                @foreach (PricingMode::cases() as $mode)
                    <flux:radio value="{{ $mode->value }}" label="{{ $mode->label() }}" />
                @endforeach
            </flux:radio.group>
            <flux:error name="pricing_mode" />

            @if (PricingMode::tryFrom($pricing_mode)?->requiresPrice())
                <flux:field>
                    <flux:label>{{ $pricing_mode === 'starting-from' ? 'Starting price (USD)' : 'Price (USD)' }} *</flux:label>
                    <flux:input wire:model="price" type="text" inputmode="decimal" placeholder="1500" />
                    <flux:error name="price" />
                </flux:field>
            @else
                <flux:text>The price is agreed with each client when they send you a request.</flux:text>
            @endif

            <div class="flex justify-end gap-3">
                <flux:modal.close>
                    <flux:button type="button" variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">Save service</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
