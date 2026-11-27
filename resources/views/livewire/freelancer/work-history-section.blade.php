<?php

use App\Actions\Profiles\UpdateProfileContent;
use App\Enums\ProfileStatus;
use App\Models\FreelancerProfile;
use Flux\Flux;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithoutUrlPagination;

new class extends Component {
    use WithPagination, WithoutUrlPagination;

    public const PER_PAGE = 12;

    public const MAX_ENTRIES = 20;

    #[Locked]
    public int $profileId = 0;

    /** The position of the entry being edited, or null when adding a new one. */
    public ?int $editingIndex = null;

    public string $company = '';

    public string $role = '';

    public string $started_at = '';

    public string $ended_at = '';

    public string $description = '';

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
     * @return list<array{company: string, role: string, started_at: string, ended_at: string|null, description: string|null}>
     */
    public function experiences(): array
    {
        return $this->profile()->workingContent()->experiences();
    }

    /**
     * One page of the list. The page lives in the component only: it is not in the URL and is not
     * remembered, so a reload starts again from the first page.
     */
    public function paginated(): LengthAwarePaginator
    {
        $items = $this->experiences();
        $lastPage = max(1, (int) ceil(count($items) / self::PER_PAGE));
        $page = min($this->getPage(), $lastPage);

        return new LengthAwarePaginator(
            array_slice($items, ($page - 1) * self::PER_PAGE, self::PER_PAGE, true),
            count($items),
            self::PER_PAGE,
            $page,
        );
    }

    public function isLocked(): bool
    {
        return $this->profile()->openRevision?->status === ProfileStatus::PendingReview;
    }

    /**
     * @param  array{started_at: string, ended_at: string|null}  $experience
     */
    public function period(array $experience): string
    {
        $ended = $experience['ended_at'] ? Carbon::parse($experience['ended_at'])->format('M Y') : 'present';

        return Carbon::parse($experience['started_at'])->format('M Y').' – '.$ended;
    }

    #[On('profile-revision-changed')]
    public function resetForm(): void
    {
        $this->reset('editingIndex', 'company', 'role', 'started_at', 'ended_at', 'description');
        $this->resetValidation();
    }

    public function startCreating(): void
    {
        $this->resetForm();

        Flux::modal('work-history-form')->show();
    }

    public function startEditing(int $index): void
    {
        $experience = $this->experiences()[$index] ?? null;

        abort_if($experience === null, 404);

        $this->resetForm();
        $this->editingIndex = $index;
        $this->company = $experience['company'];
        $this->role = $experience['role'];
        $this->started_at = $experience['started_at'];
        $this->ended_at = (string) $experience['ended_at'];
        $this->description = (string) $experience['description'];

        Flux::modal('work-history-form')->show();
    }

    public function save(UpdateProfileContent $update): void
    {
        $validated = $this->validate([
            'company' => ['required', 'string', 'max:255'],
            'role' => ['required', 'string', 'max:255'],
            'started_at' => ['required', 'date'],
            'ended_at' => ['nullable', 'date', 'after_or_equal:started_at'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $experiences = $this->experiences();

        if ($this->editingIndex === null && count($experiences) >= $this::MAX_ENTRIES) {
            $this->addError('company', 'You can list at most '.$this::MAX_ENTRIES.' positions.');

            return;
        }

        $entry = [
            'company' => trim($validated['company']),
            'role' => trim($validated['role']),
            'started_at' => $validated['started_at'],
            'ended_at' => $validated['ended_at'] ?: null,
            'description' => $validated['description'] ?: null,
        ];

        if ($this->editingIndex === null) {
            $experiences[] = $entry;
        } else {
            abort_if(! array_key_exists($this->editingIndex, $experiences), 404);

            $experiences[$this->editingIndex] = $entry;
        }

        if (! $this->persist($update, $experiences)) {
            return;
        }

        Flux::modal('work-history-form')->close();
        $this->resetForm();
    }

    public function delete(int $index, UpdateProfileContent $update): void
    {
        $experiences = $this->experiences();

        abort_if(! array_key_exists($index, $experiences), 404);

        unset($experiences[$index]);

        $this->persist($update, array_values($experiences));
    }

    /**
     * @param  list<array<string, mixed>>  $experiences
     */
    private function persist(UpdateProfileContent $update, array $experiences): bool
    {
        try {
            $update($this->profile(), ['experiences' => array_values($experiences)]);
        } catch (\DomainException $exception) {
            $this->addError('company', $exception->getMessage());

            return false;
        }

        $this->dispatch('profile-content-saved');

        return true;
    }
};
?>
<x-site.section id="work-history" heading="Work history" :count="count($this->experiences())">
    <x-slot:actions>
        @unless ($this->isLocked() || count($this->experiences()) >= $this::MAX_ENTRIES)
            <flux:button size="sm" variant="primary" icon="plus" wire:click="startCreating">Add position</flux:button>
        @endunless
    </x-slot:actions>

    <flux:text class="mb-6">The companies and roles that show clients where you have worked, up to {{ $this::MAX_ENTRIES }} positions.</flux:text>

    @php($paginator = $this->paginated())

    @if ($paginator->total() === 0)
        <x-site.empty title="No work history yet." text="Add the positions you have held." />
    @else
        <ul class="space-y-3">
            @foreach ($paginator as $index => $experience)
                <li wire:key="experience-{{ $index }}">
                    <flux:card class="flex items-start gap-4">
                        <div class="min-w-0 flex-1">
                            <flux:heading>{{ $experience['role'] }} · {{ $experience['company'] }}</flux:heading>
                            <flux:text size="sm" class="mt-1">{{ $this->period($experience) }}</flux:text>

                            @if ($experience['description'])
                                <flux:text class="mt-2 whitespace-pre-line">{{ $experience['description'] }}</flux:text>
                            @endif
                        </div>

                        @unless ($this->isLocked())
                            <div class="flex gap-2">
                                <flux:button size="sm" wire:click="startEditing({{ $index }})">Edit</flux:button>
                                <flux:button size="sm" variant="danger" wire:click="delete({{ $index }})" wire:confirm="Remove this position?">Remove</flux:button>
                            </div>
                        @endunless
                    </flux:card>
                </li>
            @endforeach
        </ul>

        @if ($paginator->hasPages())
            <flux:pagination :paginator="$paginator" class="mt-6" />
        @endif
    @endif

    <flux:modal name="work-history-form" class="md:w-[34rem]">
        <form wire:submit="save" class="space-y-5">
            <flux:heading size="lg">{{ $editingIndex === null ? 'Add position' : 'Edit position' }}</flux:heading>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>Company *</flux:label>
                    <flux:input wire:model="company" />
                    <flux:error name="company" />
                </flux:field>

                <flux:field>
                    <flux:label>Role *</flux:label>
                    <flux:input wire:model="role" />
                    <flux:error name="role" />
                </flux:field>

                <flux:field>
                    <flux:label>Start date *</flux:label>
                    <flux:input type="date" wire:model="started_at" />
                    <flux:error name="started_at" />
                </flux:field>

                <flux:field>
                    <flux:label>End date</flux:label>
                    <flux:input type="date" wire:model="ended_at" />
                    <flux:description>Leave empty if you still work there.</flux:description>
                    <flux:error name="ended_at" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>Description</flux:label>
                <flux:textarea wire:model="description" rows="3" />
                <flux:error name="description" />
            </flux:field>

            <div class="flex justify-end gap-3">
                <flux:modal.close>
                    <flux:button type="button" variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">Save position</flux:button>
            </div>
        </form>
    </flux:modal>
</x-site.section>
