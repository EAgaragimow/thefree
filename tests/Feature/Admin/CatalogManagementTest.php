<?php

use App\Filament\Resources\Industries\Pages\EditIndustry;
use App\Filament\Resources\Industries\Pages\ListIndustries;
use App\Filament\Resources\ProfessionalFunctions\Pages\EditProfessionalFunction;
use App\Filament\Resources\ProfessionalRoles\Pages\CreateProfessionalRole;
use App\Filament\Resources\ProfessionalRoles\Pages\EditProfessionalRole;
use App\Filament\Resources\ProfessionalRoles\Pages\ListProfessionalRoles;
use App\Filament\Resources\Skills\Pages\EditSkill;
use App\Filament\Resources\Tools\Pages\CreateTool;
use App\Filament\Resources\Tools\Pages\EditTool;
use App\Filament\Resources\Tools\Pages\ListTools;
use App\Filament\Support\CatalogDeletion;
use App\Models\FreelancerProfile;
use App\Models\Industry;
use App\Models\ProfessionalFunction;
use App\Models\ProfessionalRole;
use App\Models\Skill;
use App\Models\Tool;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());
});

/**
 * The text shown in the confirmation modal of the edit page's delete action.
 */
function deleteWarning(string $page, Model $record): string
{
    $component = Livewire::test($page, ['record' => $record->getKey()])->mountAction('delete');

    return $component->instance()->getMountedAction()->getModalDescription()->toHtml();
}

it('lists the catalog entries for the administrator', function () {
    $tool = Tool::factory()->create();
    $industry = Industry::factory()->create();
    $role = ProfessionalRole::factory()->create();

    Livewire::test(ListTools::class)->assertCanSeeTableRecords([$tool]);
    Livewire::test(ListIndustries::class)->assertCanSeeTableRecords([$industry]);
    Livewire::test(ListProfessionalRoles::class)->assertCanSeeTableRecords([$role]);
});

it('adds a tool exactly as typed', function () {
    Livewire::test(CreateTool::class)
        ->fillForm(['name' => 'Liverecover', 'slug' => 'liverecover'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Tool::where('name', 'Liverecover')->exists())->toBeTrue();
});

it('adds a role under a function', function () {
    $function = ProfessionalFunction::factory()->create();

    Livewire::test(CreateProfessionalRole::class)
        ->fillForm(['name' => 'Retention Lead', 'slug' => 'retention-lead', 'professional_function_id' => $function->id, 'sort_order' => 3])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(ProfessionalRole::where('slug', 'retention-lead')->firstOrFail()->professional_function_id)->toBe($function->id);
});

it('requires a function for a new role', function () {
    Livewire::test(CreateProfessionalRole::class)
        ->fillForm(['name' => 'Retention Lead', 'slug' => 'retention-lead'])
        ->call('create')
        ->assertHasFormErrors(['professional_function_id' => 'required']);
});

it('warns who still uses a skill, tool or industry before it is deleted', function (string $page, string $model) {
    $entry = $model::factory()->create(['name' => 'Catalog Entry']);
    $profile = FreelancerProfile::factory()->create();
    $profile->{match ($model) {
        Skill::class => 'skills',
        Tool::class => 'tools',
        Industry::class => 'industries',
    }}()->attach($entry);

    expect(deleteWarning($page, $entry))
        ->toContain('Catalog Entry')
        ->toContain('is used by 1 freelancer')
        ->toContain('#'.$profile->user_id.' '.e($profile->user->name));
})->with([
    'skill' => [EditSkill::class, Skill::class],
    'tool' => [EditTool::class, Tool::class],
    'industry' => [EditIndustry::class, Industry::class],
]);

it('warns who has a role as their main, secondary or third role', function () {
    $role = ProfessionalRole::factory()->create(['name' => 'CRM Marketer']);
    $main = FreelancerProfile::factory()->create(['main_role_id' => $role->id]);
    $third = FreelancerProfile::factory()->create(['third_role_id' => $role->id]);
    FreelancerProfile::factory()->create();

    expect(deleteWarning(EditProfessionalRole::class, $role))
        ->toContain('is used by 2 freelancer')
        ->toContain('#'.$main->user_id.' '.e($main->user->name))
        ->toContain('#'.$third->user_id.' '.e($third->user->name));
});

it('warns who works in a function and that its roles lose their function', function () {
    $function = ProfessionalFunction::factory()->create(['name' => 'Retention & CRM']);
    ProfessionalRole::factory()->count(2)->for($function, 'professionalFunction')->create();
    $profile = FreelancerProfile::factory()->create(['professional_function_id' => $function->id]);

    expect(deleteWarning(EditProfessionalFunction::class, $function))
        ->toContain('is used by 1 freelancer')
        ->toContain('#'.$profile->user_id.' '.e($profile->user->name))
        ->toContain('It has 2 role(s)');
});

it('does not warn about an entry nobody uses', function () {
    expect(CatalogDeletion::warning([Skill::factory()->create()]))->toBeNull();
});

it('names only the first freelancers and counts the rest', function () {
    $skill = Skill::factory()->create();
    FreelancerProfile::factory()->count(CatalogDeletion::LISTED + 3)->create()
        ->each(fn (FreelancerProfile $profile) => $profile->skills()->attach($skill));

    $warning = CatalogDeletion::warning([$skill])->toHtml();

    expect($warning)
        ->toContain('is used by '.(CatalogDeletion::LISTED + 3).' freelancer')
        ->toContain('and 3 more')
        ->and(substr_count($warning, '<li>#'))->toBe(CatalogDeletion::LISTED);
});

it('escapes freelancer names in the warning', function () {
    $skill = Skill::factory()->create();
    $user = User::factory()->freelancer()->create(['name' => '<b>Eve</b>']);
    FreelancerProfile::factory()->for($user)->create()->skills()->attach($skill);

    expect(CatalogDeletion::warning([$skill])->toHtml())
        ->toContain('&lt;b&gt;Eve&lt;/b&gt;')
        ->not->toContain('<b>Eve</b>');
});

it('still deletes a used entry once the administrator confirms', function () {
    $skill = Skill::factory()->create();
    $profile = FreelancerProfile::factory()->create();
    $profile->skills()->attach($skill);

    Livewire::test(EditSkill::class, ['record' => $skill->getKey()])
        ->callAction('delete');

    expect(Skill::find($skill->id))->toBeNull()
        ->and($profile->skills()->count())->toBe(0);
});
