<?php

use App\Actions\Directory\DirectoryPreview;
use App\Models\BrandProfile;
use App\Models\FreelancerProfile;
use App\Models\Industry;
use App\Models\ProfessionalFunction;
use App\Models\ProfessionalRole;
use Illuminate\Support\Collection;

function previewNames(?BrandProfile $brand): array
{
    return app(DirectoryPreview::class)($brand)
        ->map(fn (FreelancerProfile $profile): string => $profile->user->name)
        ->all();
}

function brandWanting(?ProfessionalFunction $function = null, ?ProfessionalRole $role = null, ?Industry $industry = null): BrandProfile
{
    $brand = BrandProfile::factory()->create(['industry_id' => $industry?->id]);

    if ($function) {
        $brand->professionalFunctions()->attach($function);
    }

    if ($role) {
        $brand->professionalRoles()->attach($role);
    }

    return $brand;
}

it('lists the best matches for the brand first', function () {
    $function = ProfessionalFunction::factory()->create();
    $role = ProfessionalRole::factory()->for($function, 'professionalFunction')->create();
    $industry = Industry::factory()->create();

    directoryProfile(['professional_function_id' => $function->id, 'main_role_id' => $role->id, 'score' => 0], ['name' => 'Function and role']);
    directoryProfile(['professional_function_id' => $function->id, 'score' => 99], ['name' => 'Function only']);
    directoryProfile(['score' => 50], ['name' => 'Unrelated']);
    directoryProfile(['professional_function_id' => $function->id, 'secondary_role_id' => $role->id], ['name' => 'Secondary role'])
        ->industries()->attach($industry);

    $names = previewNames(brandWanting($function, $role, $industry));

    expect(array_slice($names, 0, 3))->toBe(['Secondary role', 'Function and role', 'Function only']);
});

it('lists at most nine matches', function () {
    $function = ProfessionalFunction::factory()->create();
    FreelancerProfile::factory()->published()->count(12)->create(['professional_function_id' => $function->id]);

    expect(previewNames(brandWanting($function)))->toHaveCount(DirectoryPreview::MAXIMUM);
});

it('keeps all matches when there are six or more', function () {
    $function = ProfessionalFunction::factory()->create();
    FreelancerProfile::factory()->published()->count(7)->create(['professional_function_id' => $function->id]);
    directoryProfile(['score' => 1000], ['name' => 'Unrelated star']);

    $names = previewNames(brandWanting($function));

    expect($names)->toHaveCount(7)->not->toContain('Unrelated star');
});

it('tops the list up to six with the highest-scoring freelancers', function () {
    $function = ProfessionalFunction::factory()->create();
    directoryProfile(['professional_function_id' => $function->id, 'score' => 0], ['name' => 'Match']);

    foreach (range(1, 8) as $score) {
        directoryProfile(['score' => $score * 10], ['name' => "Score {$score}"]);
    }

    $names = previewNames(brandWanting($function));

    expect($names)->toBe(['Match', 'Score 8', 'Score 7', 'Score 6', 'Score 5', 'Score 4']);
});

it('puts featured freelancers first when it tops up', function () {
    directoryProfile(['score' => 100], ['name' => 'High score']);
    directoryProfile(['featured_at' => now(), 'featured_sort' => 1, 'score' => 0], ['name' => 'Featured']);

    expect(previewNames(null))->toBe(['Featured', 'High score']);
});

it('shows the best freelancers when there is no brand profile or no preferences yet', function () {
    foreach (range(1, 8) as $score) {
        directoryProfile(['score' => $score], ['name' => "Score {$score}"]);
    }

    $expected = ['Score 8', 'Score 7', 'Score 6', 'Score 5', 'Score 4', 'Score 3'];

    expect(previewNames(null))->toBe($expected)
        ->and(previewNames(brandWanting()))->toBe($expected);
});

it('returns everyone when fewer than six freelancers exist', function () {
    directoryProfile(user: ['name' => 'One']);
    directoryProfile(user: ['name' => 'Two']);

    expect(previewNames(null))->toHaveCount(2);
});

it('returns nothing when nobody is published', function () {
    directoryProfile(['published_at' => null]);

    expect(app(DirectoryPreview::class)(null))->toBeInstanceOf(Collection::class)->toBeEmpty();
});

it('never previews freelancers who are hidden', function () {
    $function = ProfessionalFunction::factory()->create();
    directoryProfile(['professional_function_id' => $function->id], ['name' => 'Visible']);
    directoryProfile(['professional_function_id' => $function->id], ['name' => 'Suspended', 'status' => 'suspended']);
    directoryProfile(['professional_function_id' => $function->id, 'published_at' => null], ['name' => 'Unpublished']);

    expect(previewNames(brandWanting($function)))->toBe(['Visible']);
});
