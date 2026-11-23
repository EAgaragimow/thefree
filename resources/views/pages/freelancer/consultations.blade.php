<?php

use App\Actions\Profiles\ConsultationSchedule;
use App\Actions\Profiles\ProfileContent;
use App\Actions\Profiles\UpdateProfileContent;
use App\Enums\ConsultationType;
use App\Enums\ProfileStatus;
use App\Enums\Weekday;
use App\Models\FreelancerProfile;
use Flux\Flux;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Consultations')] class extends Component {
    #[Locked]
    public int $profileId = 0;

    /** The consultation type being configured: "free" or "paid". Null while the form is closed. */
    public ?string $editingType = null;

    public bool $enabled = false;

    public string $duration_minutes = '30';

    public string $price = '';

    public string $description = '';

    /**
     * The weekly schedule being edited, keyed by day: ['mon' => ['on' => true, 'start' => '09:00', 'end' => '17:00']].
     *
     * @var array<string, array{on: bool, start: string, end: string}>
     */
    public array $availability = [];

    public function mount(): void
    {
        $profile = auth()->user()->freelancerProfile;

        abort_if($profile === null, 404);

        $this->profileId = $profile->id;
        $this->resetForm();
    }

    public function profile(): FreelancerProfile
    {
        return FreelancerProfile::query()
            ->with('openRevision')
            ->where('user_id', auth()->id())
            ->findOrFail($this->profileId);
    }

    /**
     * Both consultation types, free first, with the freelancer's current (possibly unpublished) settings.
     *
     * @return list<array<string, mixed>>
     */
    public function offers(): array
    {
        return $this->profile()->workingContent()->consultationOffers();
    }

    public function timezone(): string
    {
        return (string) $this->profile()->workingContent()->get('timezone');
    }

    public function isLocked(): bool
    {
        return $this->profile()->openRevision?->status === ProfileStatus::PendingReview;
    }

    #[On('profile-revision-changed')]
    public function resetForm(): void
    {
        $this->reset('editingType', 'enabled', 'price', 'description');
        $this->duration_minutes = (string) ProfileContent::DEFAULT_CONSULTATION_MINUTES;
        $this->availability = self::emptySchedule();
        $this->resetValidation();
    }

    public function edit(string $type): void
    {
        $offer = collect($this->offers())->firstWhere('type', $type);

        abort_if($offer === null, 404);

        $this->resetForm();
        $this->editingType = $type;
        $this->enabled = $offer['enabled'];
        $this->duration_minutes = (string) $offer['duration_minutes'];
        $this->price = $offer['price_cents'] > 0 ? number_format($offer['price_cents'] / 100, 2, '.', '') : '';
        $this->description = (string) $offer['description'];

        foreach ($offer['availability'] as $window) {
            $this->availability[$window['day']] = ['on' => true, 'start' => $window['start'], 'end' => $window['end']];
        }

        Flux::modal('consultation-form')->show();
    }

    public function save(UpdateProfileContent $update): void
    {
        $type = ConsultationType::tryFrom((string) $this->editingType);

        abort_if($type === null, 404);

        $this->validate([
            'duration_minutes' => ['required', 'integer', 'min:'.ProfileContent::MIN_CONSULTATION_MINUTES, 'max:'.ProfileContent::MAX_CONSULTATION_MINUTES],
            'price' => $type->isPaid()
                ? ['required', 'numeric', 'min:'.(ProfileContent::MIN_PAID_CONSULTATION_CENTS / 100), 'max:100000', 'regex:/^\d+(\.\d{1,2})?$/']
                : ['nullable'],
            'description' => ['nullable', 'string', 'max:1000'],
            'availability.*.on' => ['boolean'],
            'availability.*.start' => ['required', 'in:'.implode(',', ConsultationSchedule::options())],
            'availability.*.end' => ['required', 'in:'.implode(',', ConsultationSchedule::options(forEnd: true))],
        ], [
            'price.required' => 'Enter a price of at least $1. For a call without a price, use the free consultation.',
            'price.min' => 'A paid consultation costs at least $1.',
            'price.regex' => 'Use a price with at most two decimals, for example 120 or 99.50.',
            'availability.*.start.in' => 'Choose a start time from the list (24-hour format).',
            'availability.*.end.in' => 'Choose an end time from the list (24-hour format).',
        ]);

        $values = [
            'type' => $type->value,
            'enabled' => $this->enabled,
            'duration_minutes' => (int) $this->duration_minutes,
            'price_cents' => $type->isPaid() ? (int) round((float) $this->price * 100) : 0,
            'description' => $this->description,
            'availability' => collect($this->availability)
                ->filter(fn (array $window): bool => $window['on'])
                ->map(fn (array $window, string $day): array => ['day' => $day, 'start' => $window['start'], 'end' => $window['end']])
                ->values()
                ->all(),
        ];

        $normalized = collect(ProfileContent::fromArray(['consultation_offers' => [$values]])->consultationOffers())->firstWhere('type', $type->value);
        $problems = ProfileContent::consultationProblems($normalized);

        if ($problems !== []) {
            $this->addError('availability', implode(' ', $problems));

            return;
        }

        $offers = array_map(
            fn (array $offer): array => $offer['type'] === $type->value ? $values : $offer,
            $this->offers(),
        );

        try {
            $update($this->profile(), ['consultation_offers' => $offers]);
        } catch (\DomainException $exception) {
            $this->addError('availability', $exception->getMessage());

            return;
        }

        $this->dispatch('profile-content-saved');

        Flux::modal('consultation-form')->close();
        $this->resetForm();
    }

    /**
     * @return array<string, array{on: bool, start: string, end: string}>
     */
    private static function emptySchedule(): array
    {
        return collect(Weekday::cases())
            ->mapWithKeys(fn (Weekday $day): array => [$day->value => ['on' => false, 'start' => ConsultationSchedule::DEFAULT_START, 'end' => ConsultationSchedule::DEFAULT_END]])
            ->all();
    }
};
?>

<div class="mx-auto w-full max-w-3xl space-y-6">
    <div>
        <flux:heading size="xl" level="1">Consultations</flux:heading>
        <flux:text class="mt-1">
            You have two kinds of calls: a free one and a paid one. Switch each on or off and set how long it lasts and when clients can book it.
            Clients book a slot, you approve it, and the call takes place on Google Meet.
        </flux:text>
    </div>

    <livewire:profile-revision-bar />

    <div class="space-y-4">
        @foreach ($this->offers() as $offer)
            @php($type = ConsultationType::from($offer['type']))

            <flux:card wire:key="consultation-{{ $offer['type'] }}" class="space-y-3">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <flux:heading size="lg">{{ $type->label() }}</flux:heading>
                            @if ($offer['enabled'])
                                <flux:badge color="green" size="sm">Offered</flux:badge>
                            @else
                                <flux:badge size="sm">Not offered</flux:badge>
                            @endif
                        </div>

                        <flux:text class="mt-1">
                            {{ $offer['duration_minutes'] }} minutes ·
                            {{ $type->isPaid() ? '$'.number_format($offer['price_cents'] / 100, 2) : 'Free' }}
                        </flux:text>
                    </div>

                    @unless ($this->isLocked())
                        <flux:button size="sm" wire:click="edit('{{ $offer['type'] }}')">Configure</flux:button>
                    @endunless
                </div>

                @if ($offer['description'])
                    <flux:text>{{ $offer['description'] }}</flux:text>
                @endif

                @if ($offer['availability'] !== [])
                    <ul class="space-y-0.5 text-sm text-zinc-600 dark:text-zinc-400">
                        @foreach (ConsultationSchedule::summary($offer['availability']) as $line)
                            <li>{{ $line }}</li>
                        @endforeach
                    </ul>
                    <flux:text size="sm">Times are in {{ $this->timezone() }}.</flux:text>
                @else
                    <flux:text size="sm">No available days set yet.</flux:text>
                @endif
            </flux:card>
        @endforeach
    </div>

    <flux:modal name="consultation-form" class="md:w-[36rem]">
        <form wire:submit="save" class="space-y-5">
            <flux:heading size="lg">
                {{ $editingType === null ? 'Configure consultation' : 'Configure '.strtolower(ConsultationType::from($editingType)->label()) }}
            </flux:heading>

            <flux:switch wire:model="enabled" label="Offer this consultation" description="Clients only see the consultations you offer." />

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>Length (minutes)</flux:label>
                    <flux:input wire:model="duration_minutes" type="number" min="{{ ProfileContent::MIN_CONSULTATION_MINUTES }}" max="{{ ProfileContent::MAX_CONSULTATION_MINUTES }}" step="5" />
                    <flux:error name="duration_minutes" />
                </flux:field>

                @if ($editingType === ConsultationType::Paid->value)
                    <flux:field>
                        <flux:label>Price (USD)</flux:label>
                        <flux:input wire:model="price" type="text" inputmode="decimal" placeholder="120" />
                        <flux:error name="price" />
                    </flux:field>
                @else
                    <flux:field>
                        <flux:label>Price</flux:label>
                        <flux:input value="Free" disabled />
                    </flux:field>
                @endif
            </div>

            <flux:field>
                <flux:label>Description</flux:label>
                <flux:textarea wire:model="description" rows="3" placeholder="What clients can expect from this call." />
                <flux:error name="description" />
            </flux:field>

            <div class="space-y-2">
                <div>
                    <flux:heading>Availability</flux:heading>
                    <flux:text size="sm">Pick the days you take these calls and the hours, in 24-hour format. Times are in {{ $this->timezone() }}, your profile time zone.</flux:text>
                </div>

                @foreach (Weekday::cases() as $day)
                    <div wire:key="day-{{ $day->value }}" class="grid grid-cols-[8rem_1fr_1fr] items-center gap-3">
                        <flux:checkbox wire:model.live="availability.{{ $day->value }}.on" label="{{ $day->label() }}" />

                        <flux:select wire:model="availability.{{ $day->value }}.start" :disabled="! ($availability[$day->value]['on'] ?? false)" aria-label="{{ $day->label() }} from">
                            @foreach (ConsultationSchedule::options() as $time)
                                <flux:select.option value="{{ $time }}">{{ $time }}</flux:select.option>
                            @endforeach
                        </flux:select>

                        <flux:select wire:model="availability.{{ $day->value }}.end" :disabled="! ($availability[$day->value]['on'] ?? false)" aria-label="{{ $day->label() }} to">
                            @foreach (ConsultationSchedule::options(forEnd: true) as $time)
                                <flux:select.option value="{{ $time }}">{{ $time }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                @endforeach

                <flux:error name="availability" />
            </div>

            <div class="flex justify-end gap-3">
                <flux:modal.close>
                    <flux:button type="button" variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">Save consultation</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
