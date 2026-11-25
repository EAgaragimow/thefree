<?php

namespace App\Actions\Directory;

use App\Models\BrandProfile;
use App\Models\FreelancerProfile;
use Illuminate\Support\Collection;

/**
 * The 6–9 freelancers a Free (or payment-restricted) client sees instead of the full directory,
 * and the recommendations on the client dashboard (SPEC §2).
 *
 * They are the visible freelancers that best match the brand's saved functions, roles and
 * industry, best first. When fewer than {@see MINIMUM} match, the list is topped up with the
 * highest-scoring freelancers (Featured ones first, then newbies), as far as that many exist.
 */
class DirectoryPreview
{
    public const MINIMUM = 6;

    public const MAXIMUM = 9;

    /**
     * @return Collection<int, FreelancerProfile>
     */
    public function __invoke(?BrandProfile $brand): Collection
    {
        $matches = $brand === null ? collect() : $this->bestMatches($brand);

        if ($matches->count() >= self::MINIMUM) {
            return $matches;
        }

        $fill = FreelancerProfile::query()->visible()
            ->whereNotIn('freelancer_profiles.id', $matches->pluck('id')->all())
            ->orderByRaw('featured_at is null')
            ->orderBy('featured_sort')
            ->orderByDesc('is_newbie')
            ->orderByDesc('score')
            ->orderBy('freelancer_profiles.id')
            ->forCard()
            ->limit(self::MINIMUM - $matches->count())
            ->get();

        return $matches->concat($fill)->values();
    }

    /**
     * @return Collection<int, FreelancerProfile>
     */
    private function bestMatches(BrandProfile $brand): Collection
    {
        $functionIds = $brand->professionalFunctions()->pluck('professional_functions.id')->all();
        $roleIds = $brand->professionalRoles()->pluck('professional_roles.id')->all();

        [$weight, $bindings] = $this->weight($functionIds, $roleIds, $brand->industry_id);

        if ($bindings === []) {
            return collect();
        }

        return FreelancerProfile::query()->visible()
            ->whereRaw("({$weight}) > 0", $bindings)
            ->orderByRaw("({$weight}) desc", $bindings)
            ->orderByDesc('is_newbie')
            ->orderByDesc('score')
            ->orderBy('freelancer_profiles.id')
            ->forCard()
            ->limit(self::MAXIMUM)
            ->get();
    }

    /**
     * SQL that scores a freelancer against the brand: 1 for the same function, 2 for each of
     * the brand's roles the freelancer holds, 1 for experience in the brand's industry.
     *
     * @param  list<int>  $functionIds
     * @param  list<int>  $roleIds
     * @return array{0: string, 1: list<int>}
     */
    private function weight(array $functionIds, array $roleIds, ?int $industryId): array
    {
        $parts = [];
        $bindings = [];
        $marks = fn (array $ids): string => implode(', ', array_fill(0, count($ids), '?'));

        if ($functionIds !== []) {
            $parts[] = "case when freelancer_profiles.professional_function_id in ({$marks($functionIds)}) then 1 else 0 end";
            array_push($bindings, ...$functionIds);
        }

        if ($roleIds !== []) {
            foreach (['main_role_id', 'secondary_role_id', 'third_role_id'] as $column) {
                $parts[] = "case when freelancer_profiles.{$column} in ({$marks($roleIds)}) then 2 else 0 end";
                array_push($bindings, ...$roleIds);
            }
        }

        if ($industryId !== null) {
            $parts[] = 'case when exists (select 1 from freelancer_profile_industry where freelancer_profile_industry.freelancer_profile_id = freelancer_profiles.id and freelancer_profile_industry.industry_id = ?) then 1 else 0 end';
            $bindings[] = $industryId;
        }

        return [implode(' + ', $parts), $bindings];
    }
}
