<?php

namespace App\Actions\Directory;

use App\Enums\WeeklyHours;
use App\Models\FreelancerProfile;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * The full freelancer directory (Paid clients, freelancers, admins): search, filters, sorting and
 * the Featured block. It reads live, visible profiles only, so edits that are still waiting for
 * review never show up here.
 *
 * Every multi-select filter matches a freelancer who has any of the selected entries.
 *
 * @phpstan-type Filters array{
 *     search?: string,
 *     function_id?: int|string|null,
 *     role_ids?: list<int|string>,
 *     skill_ids?: list<int|string>,
 *     tool_ids?: list<int|string>,
 *     industry_ids?: list<int|string>,
 *     rate_min?: int|string|null,
 *     rate_max?: int|string|null,
 *     weekly_hours?: string|null,
 * }
 */
class DirectoryQuery
{
    public const PER_PAGE = 12;

    /** Most words of a search that are taken into account. */
    public const MAX_SEARCH_WORDS = 5;

    public const SORT_BEST = 'best';

    public const SORT_RATE_ASC = 'rate_asc';

    public const SORT_RATE_DESC = 'rate_desc';

    public const SORT_NEWEST = 'newest';

    /**
     * @return array<string, string> Sort key => label.
     */
    public static function sorts(): array
    {
        return [
            self::SORT_BEST => 'Best match',
            self::SORT_RATE_ASC => 'Hourly rate: low to high',
            self::SORT_RATE_DESC => 'Hourly rate: high to low',
            self::SORT_NEWEST => 'Newest',
        ];
    }

    /**
     * One page of the regular list. Featured freelancers are listed separately
     * ({@see featured()}), so they are left out here.
     *
     * @param  Filters  $filters
     * @return LengthAwarePaginator<int, FreelancerProfile>
     */
    public function paginate(array $filters, string $sort = self::SORT_BEST): LengthAwarePaginator
    {
        $query = $this->filtered($filters)->whereNull('featured_at');

        $this->applySort($query, $sort, $filters);

        return $query->forCard()->paginate(self::PER_PAGE);
    }

    /**
     * Featured freelancers that match the filters, in the order the admin chose.
     *
     * @param  Filters  $filters
     * @return Collection<int, FreelancerProfile>
     */
    public function featured(array $filters): Collection
    {
        return $this->filtered($filters)
            ->whereNotNull('featured_at')
            ->orderBy('featured_sort')
            ->orderBy('id')
            ->forCard()
            ->get();
    }

    /**
     * Visible profiles that satisfy the filters, unsorted.
     *
     * @param  Filters  $filters
     * @return Builder<FreelancerProfile>
     */
    public function filtered(array $filters): Builder
    {
        $query = FreelancerProfile::query()->visible();

        foreach ($this->words($filters['search'] ?? '') as $word) {
            $this->matchWord($query, $word);
        }

        if (filled($filters['function_id'] ?? null)) {
            $query->where('professional_function_id', (int) $filters['function_id']);
        }

        if ($roles = $this->ids($filters['role_ids'] ?? [])) {
            $query->where(fn (Builder $profiles) => $profiles
                ->whereIn('main_role_id', $roles)
                ->orWhereIn('secondary_role_id', $roles)
                ->orWhereIn('third_role_id', $roles));
        }

        foreach (['skill_ids' => 'skills', 'tool_ids' => 'tools', 'industry_ids' => 'industries'] as $key => $relation) {
            if ($ids = $this->ids($filters[$key] ?? [])) {
                $query->whereHas($relation, fn (Builder $related) => $related->whereIn($related->getModel()->getQualifiedKeyName(), $ids));
            }
        }

        if (filled($filters['rate_min'] ?? null)) {
            $query->where('hourly_rate_cents', '>=', (int) $filters['rate_min'] * 100);
        }

        if (filled($filters['rate_max'] ?? null)) {
            $query->where('hourly_rate_cents', '<=', (int) $filters['rate_max'] * 100);
        }

        if (WeeklyHours::tryFrom((string) ($filters['weekly_hours'] ?? '')) !== null) {
            $query->where('weekly_hours', $filters['weekly_hours']);
        }

        return $query;
    }

    /**
     * A word has to appear in the name, title, about text, a skill or a service title.
     *
     * @param  Builder<FreelancerProfile>  $query
     */
    private function matchWord(Builder $query, string $word): void
    {
        $pattern = $this->pattern($word);

        $query->where(function (Builder $profiles) use ($pattern): void {
            $profiles->whereHas('user', fn (Builder $users) => $this->like($users, 'users.name', $pattern))
                ->orWhere(fn (Builder $title) => $this->like($title, 'freelancer_profiles.professional_title', $pattern))
                ->orWhere(fn (Builder $about) => $this->like($about, 'freelancer_profiles.about', $pattern))
                ->orWhereHas('skills', fn (Builder $skills) => $this->like($skills, 'skills.name', $pattern))
                ->orWhereHas('services', fn (Builder $services) => $this->like($services, 'services.title', $pattern));
        });
    }

    /**
     * @param  Builder<FreelancerProfile>  $query
     * @param  Filters  $filters
     */
    private function applySort(Builder $query, string $sort, array $filters): void
    {
        match ($sort) {
            self::SORT_RATE_ASC => $query->orderByRaw('hourly_rate_cents is null')->orderBy('hourly_rate_cents'),
            self::SORT_RATE_DESC => $query->orderByRaw('hourly_rate_cents is null')->orderByDesc('hourly_rate_cents'),
            self::SORT_NEWEST => $query->orderByDesc('published_at'),
            default => $this->orderByBestMatch($query, trim((string) ($filters['search'] ?? ''))),
        };

        $query->orderByDesc('score')->orderBy('freelancer_profiles.id');
    }

    /**
     * Best match: the search relevance first, then newbies (their first month, so a new freelancer
     * is not buried under established ones), then the freelancer score, then the newest profiles.
     *
     * @param  Builder<FreelancerProfile>  $query
     */
    private function orderByBestMatch(Builder $query, string $search): void
    {
        $this->orderByRelevance($query, $search);

        $query->orderByDesc('is_newbie')->orderByDesc('score')->orderByDesc('freelancer_profiles.created_at');
    }

    /**
     * With a search, whoever the whole phrase names or titles comes first; then the freelancer score.
     *
     * @param  Builder<FreelancerProfile>  $query
     */
    private function orderByRelevance(Builder $query, string $search): void
    {
        if ($search === '') {
            return;
        }

        $pattern = $this->pattern($search);

        $query->orderByRaw(
            "(case when (select name from users where users.id = freelancer_profiles.user_id) like ? escape '!' then 2 else 0 end"
            ." + case when freelancer_profiles.professional_title like ? escape '!' then 1 else 0 end) desc",
            [$pattern, $pattern],
        );
    }

    /**
     * @param  Builder<*>  $query
     */
    private function like(Builder $query, string $column, string $pattern): void
    {
        $query->whereRaw("{$column} like ? escape '!'", [$pattern]);
    }

    /**
     * A `LIKE` pattern that contains the text, with the wildcard characters made literal.
     */
    private function pattern(string $text): string
    {
        return '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], trim($text)).'%';
    }

    /**
     * @return list<string>
     */
    private function words(string $search): array
    {
        $words = preg_split('/\s+/u', trim($search), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_slice($words, 0, self::MAX_SEARCH_WORDS);
    }

    /**
     * @param  list<int|string>  $values
     * @return list<int>
     */
    private function ids(array $values): array
    {
        return collect($values)->filter(fn ($value): bool => filled($value))->map(fn ($value): int => (int) $value)->unique()->values()->all();
    }
}
