<?php

use App\Actions\Directory\DirectoryPreview;
use App\Actions\Directory\DirectoryQuery;
use App\Actions\Directory\ToggleFavorite;
use App\Enums\WeeklyHours;
use App\Models\FreelancerProfile;
use App\Models\Industry;
use App\Models\ProfessionalFunction;
use App\Models\ProfessionalRole;
use App\Models\Skill;
use App\Models\Tool;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Freelancers')] class extends Component {
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'function', except: '')]
    public string $function_id = '';

    /** @var list<int|string> */
    #[Url(as: 'roles', except: [])]
    public array $role_ids = [];

    /** @var list<int|string> */
    #[Url(as: 'skills', except: [])]
    public array $skill_ids = [];

    /** @var list<int|string> */
    #[Url(as: 'tools', except: [])]
    public array $tool_ids = [];

    /** @var list<int|string> */
    #[Url(as: 'industries', except: [])]
    public array $industry_ids = [];

    #[Url(as: 'rate_min', except: '')]
    public string $rate_min = '';

    #[Url(as: 'rate_max', except: '')]
    public string $rate_max = '';

    #[Url(as: 'hours', except: '')]
    public string $weekly_hours = '';

    #[Url(except: 'best')]
    public string $sort = DirectoryQuery::SORT_BEST;

    /** Bumped when the filters are cleared so the multi-selects redraw empty. */
    public int $filterVersion = 0;

    /**
     * Any filter change starts again from the first page.
     */
    public function updated(string $name): void
    {
        if ($name !== 'paginators.page') {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'function_id', 'role_ids', 'skill_ids', 'tool_ids', 'industry_ids', 'rate_min', 'rate_max', 'weekly_hours');
        $this->filterVersion++;
        $this->resetPage();
    }

    public function toggleFavorite(int $profileId): void
    {
        $profile = FreelancerProfile::query()->visible()->findOrFail($profileId);

        app(ToggleFavorite::class)(auth()->user(), $profile);
    }

    public function hasFullAccess(): bool
    {
        return Gate::allows('view-full-directory');
    }

    public function hasActiveFilters(): bool
    {
        return $this->filters() !== $this->emptyFilters();
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return [
            'search' => trim($this->search),
            'function_id' => $this->function_id,
            'role_ids' => $this->role_ids,
            'skill_ids' => $this->skill_ids,
            'tool_ids' => $this->tool_ids,
            'industry_ids' => $this->industry_ids,
            'rate_min' => $this->rate_min,
            'rate_max' => $this->rate_max,
            'weekly_hours' => $this->weekly_hours,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyFilters(): array
    {
        return [
            'search' => '', 'function_id' => '', 'role_ids' => [], 'skill_ids' => [], 'tool_ids' => [], 'industry_ids' => [],
            'rate_min' => '', 'rate_max' => '', 'weekly_hours' => '',
        ];
    }

    public function sortKey(): string
    {
        return array_key_exists($this->sort, DirectoryQuery::sorts()) ? $this->sort : DirectoryQuery::SORT_BEST;
    }

    /**
     * Ids of the freelancers the signed-in user has saved.
     *
     * @return list<int>
     */
    public function favoriteIds(): array
    {
        return auth()->user()->favoriteFreelancers()->pluck('freelancer_profiles.id')->all();
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
     * @return list<array{id: string, name: string, group: string}>
     */
    public function roleOptions(): array
    {
        return ProfessionalRole::selectOptions();
    }

    /**
     * @param  class-string<Skill|Tool|Industry>  $model
     * @return list<array{id: string, name: string}>
     */
    public function catalogOptions(string $model): array
    {
        return $model::query()->orderBy('name')->get(['id', 'name'])
            ->map(fn ($entry): array => ['id' => (string) $entry->id, 'name' => $entry->name])
            ->all();
    }

    /**
     * What the page lists: the whole directory with filters, or the preview a Free client gets.
     *
     * @return array{full: bool, featured: iterable<FreelancerProfile>, results: iterable<FreelancerProfile>, paginator: \Illuminate\Contracts\Pagination\LengthAwarePaginator|null}
     */
    public function listing(): array
    {
        if (! $this->hasFullAccess()) {
            return [
                'full' => false,
                'featured' => [],
                'results' => app(DirectoryPreview::class)(auth()->user()->brandProfile)->sortByDesc('score')->values(),
                'paginator' => null,
            ];
        }

        $query = app(DirectoryQuery::class);
        $paginator = $query->paginate($this->filters(), $this->sortKey());

        return [
            'full' => true,
            'featured' => $paginator->onFirstPage() ? $query->featured($this->filters()) : [],
            'results' => $paginator,
            'paginator' => $paginator,
        ];
    }
};
?>

<div class="space-y-6">
    @php($listing = $this->listing())
    @php($favoriteIds = $this->favoriteIds())
    @php($canFavorite = Gate::allows('favorite-freelancers'))

    <div>
        <flux:heading size="xl" level="1">Freelancers</flux:heading>
        <flux:text class="mt-1">
            @if ($listing['full'])
                Find the right freelancer for your next project.
            @else
                A selection of freelancers that match your brand profile. Upgrade to the Paid Plan to browse everyone and open full profiles.
            @endif
        </flux:text>
    </div>

    @if ($listing['full'])
        <form class="space-y-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700" x-on:submit.prevent>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <flux:field class="lg:col-span-2">
                    <flux:label>Search</flux:label>
                    <flux:input wire:model.live.debounce.400ms="search" type="search" placeholder="Name, profession, skill or keyword" icon="magnifying-glass" />
                </flux:field>

                <flux:field>
                    <flux:label>Function</flux:label>
                    <flux:select wire:model.live="function_id">
                        <flux:select.option value="">Any function</flux:select.option>
                        @foreach ($this->functionOptions() as $function)
                            <flux:select.option wire:key="function-{{ $function['id'] }}" value="{{ $function['id'] }}">{{ $function['name'] }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:field>

                <flux:field>
                    <flux:label>Sort by</flux:label>
                    <flux:select wire:model.live="sort">
                        @foreach (\App\Actions\Directory\DirectoryQuery::sorts() as $key => $label)
                            <flux:select.option wire:key="sort-{{ $key }}" value="{{ $key }}">{{ $label }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:field>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <x-multi-select wire:key="filter-roles-{{ $filterVersion }}" wire:model.live="role_ids" label="Roles" :options="$this->roleOptions()" />
                <x-multi-select wire:key="filter-skills-{{ $filterVersion }}" wire:model.live="skill_ids" label="Skills" :options="$this->catalogOptions(\App\Models\Skill::class)" />
                <x-multi-select wire:key="filter-tools-{{ $filterVersion }}" wire:model.live="tool_ids" label="Tools and platforms" :options="$this->catalogOptions(\App\Models\Tool::class)" />
                <x-multi-select wire:key="filter-industries-{{ $filterVersion }}" wire:model.live="industry_ids" label="Industry experience" :options="$this->catalogOptions(\App\Models\Industry::class)" />
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <flux:field>
                    <flux:label>Min. hourly rate (USD)</flux:label>
                    <flux:input wire:model.live.debounce.500ms="rate_min" type="number" min="0" step="1" />
                </flux:field>

                <flux:field>
                    <flux:label>Max. hourly rate (USD)</flux:label>
                    <flux:input wire:model.live.debounce.500ms="rate_max" type="number" min="0" step="1" />
                </flux:field>

                <flux:field>
                    <flux:label>Hours per week</flux:label>
                    <flux:select wire:model.live="weekly_hours">
                        <flux:select.option value="">Any</flux:select.option>
                        @foreach (\App\Enums\WeeklyHours::cases() as $hours)
                            <flux:select.option wire:key="hours-{{ $hours->value }}" value="{{ $hours->value }}">{{ $hours->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:field>
            </div>

            @if ($this->hasActiveFilters())
                <div>
                    <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="clearFilters">Clear filters</flux:button>
                </div>
            @endif
        </form>
    @endif

    @if (count($listing['featured']))
        <section class="space-y-3">
            <flux:heading size="lg">Featured</flux:heading>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($listing['featured'] as $profile)
                    <x-freelancer-card
                        wire:key="featured-{{ $profile->id }}"
                        :profile="$profile"
                        :href="route('directory.show', $profile)"
                        :can-favorite="$canFavorite"
                        :favorited="in_array($profile->id, $favoriteIds, true)"
                    />
                @endforeach
            </div>
        </section>
    @endif

    <section class="space-y-3">
        @if ($listing['full'] && count($listing['featured']))
            <flux:heading size="lg">All freelancers</flux:heading>
        @endif

        @if (count($listing['results']) === 0 && count($listing['featured']) === 0)
            <flux:callout icon="magnifying-glass" heading="No freelancers found">
                @if ($listing['full'] && $this->hasActiveFilters())
                    Try removing some filters or searching for something broader.
                @else
                    There are no published freelancers yet. Please check back soon.
                @endif
            </flux:callout>
        @else
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($listing['results'] as $profile)
                    <x-freelancer-card
                        wire:key="profile-{{ $profile->id }}"
                        :profile="$profile"
                        :href="$listing['full'] ? route('directory.show', $profile) : null"
                        :locked="! $listing['full']"
                        :can-favorite="$canFavorite"
                        :favorited="in_array($profile->id, $favoriteIds, true)"
                    />
                @endforeach
            </div>
        @endif

        @if ($listing['paginator'])
            <flux:pagination :paginator="$listing['paginator']" />
        @endif
    </section>

    @unless ($listing['full'])
        <x-upgrade-modal />
    @endunless
</div>
