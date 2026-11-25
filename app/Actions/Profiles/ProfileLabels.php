<?php

namespace App\Actions\Profiles;

use App\Models\Industry;
use App\Models\ProfessionalFunction;
use App\Models\ProfessionalRole;
use App\Models\Skill;
use App\Models\Tool;

/**
 * The catalog names a profile's content refers to by id, ready to show on a profile page.
 */
class ProfileLabels
{
    /**
     * Roles keep the order they were picked in (main role first); everything else is alphabetical.
     *
     * @return array{professionalFunction: string|null, roles: list<string>, skills: list<string>, tools: list<string>, industries: list<string>}
     */
    public function __invoke(ProfileContent $content): array
    {
        $roleNames = ProfessionalRole::query()->whereIn('id', $content->roleIds())->pluck('name', 'id');

        return [
            'professionalFunction' => $content->professionalFunctionId() === null
                ? null
                : ProfessionalFunction::query()->whereKey($content->professionalFunctionId())->value('name'),
            'roles' => collect($content->roleIds())->map(fn (int $id): ?string => $roleNames->get($id))->filter()->values()->all(),
            'skills' => Skill::query()->whereIn('id', $content->skillIds())->orderBy('name')->pluck('name')->all(),
            'tools' => Tool::query()->whereIn('id', $content->toolIds())->orderBy('name')->pluck('name')->all(),
            'industries' => Industry::query()->whereIn('id', $content->industryIds())->orderBy('name')->pluck('name')->all(),
        ];
    }
}
