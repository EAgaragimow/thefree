<?php

use App\Actions\Profiles\ProfileContent;
use App\Enums\ApplicationStatus;
use App\Models\FreelancerApplication;
use App\Models\ProfessionalRole;
use App\Models\Skill;
use App\Models\User;
use App\Support\Locations;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Livewire;

function validApplication(array $overrides = []): array
{
    $skills = collect(['Figma', 'Branding'])->map(fn (string $name) => Skill::query()->firstOrCreate(['slug' => Str::slug($name)], ['name' => $name]));

    $roles = collect(['Brand Marketer', 'Copywriter', 'Head of Growth', 'Producer'])->map(
        fn (string $name) => ProfessionalRole::query()->where('slug', Str::slug($name))->first()
            ?? ProfessionalRole::factory()->create(['name' => $name, 'slug' => Str::slug($name)])
    );

    return array_merge([
        'full_name' => 'Ada Freelancer',
        'email' => 'ada@example.com',
        'country' => 'Ukraine',
        'state' => 'Kyiv',
        'city' => Locations::cities('Ukraine', 'Kyiv')[0],
        'role_ids' => [(string) $roles[0]->id, (string) $roles[1]->id],
        'years_experience' => 7,
        'skill_ids' => $skills->pluck('id')->map(fn ($id) => (string) $id)->all(),
        'experience_summary' => str_repeat('I have designed brands for many companies. ', 3),
        'portfolio_links' => "https://ada.test\nhttps://behance.test/ada",
        'linkedin' => 'https://linkedin.com/in/ada',
    ], $overrides);
}

function fillApplication($component, array $data)
{
    foreach ($data as $key => $value) {
        $component->set($key, $value);
    }

    return $component;
}

it('is visible to guests', function () {
    $this->get(route('freelancer.apply'))->assertOk()->assertSee('Become a Freelancer');
});

it('stores a pending application with the chosen catalog skills and links', function () {
    fillApplication(Livewire::test('pages::freelancer.apply'), validApplication())
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('submitted', true);

    $application = FreelancerApplication::firstOrFail();

    expect($application->status)->toBe(ApplicationStatus::Pending)
        ->and($application->email)->toBe('ada@example.com')
        ->and($application->skills)->toBe(['Branding', 'Figma'])
        ->and($application->roles)->toBe(['Brand Marketer', 'Copywriter'])
        ->and($application->specialty)->toBe('Brand Marketer')
        ->and($application->country)->toBe('Ukraine')
        ->and($application->state)->toBe('Kyiv')
        ->and($application->city)->toBe(Locations::cities('Ukraine', 'Kyiv')[0])
        ->and($application->location)->toBe(Locations::cities('Ukraine', 'Kyiv')[0].', Kyiv, Ukraine')
        ->and($application->portfolio_links)->toBe(['https://ada.test', 'https://behance.test/ada'])
        ->and($application->social_links)->toBe(['linkedin' => 'https://linkedin.com/in/ada']);
});

it('requires the core fields', function () {
    Livewire::test('pages::freelancer.apply')
        ->call('submit')
        ->assertHasErrors(['full_name', 'email', 'country', 'role_ids', 'skill_ids', 'experience_summary', 'portfolio_links']);

    expect(FreelancerApplication::count())->toBe(0);
});

it('only accepts skills from the catalog and at most the profile limit', function () {
    $component = Livewire::test('pages::freelancer.apply');

    fillApplication($component, validApplication(['skill_ids' => ['9999']]))
        ->call('submit')
        ->assertHasErrors('skill_ids.0');

    $tooMany = Skill::factory()->count(ProfileContent::MAX_SKILLS + 1)->create()->pluck('id')->map(fn ($id) => (string) $id)->all();

    fillApplication(Livewire::test('pages::freelancer.apply'), validApplication(['skill_ids' => $tooMany]))
        ->call('submit')
        ->assertHasErrors('skill_ids');

    expect(FreelancerApplication::count())->toBe(0);
});

it('keeps the roles in the order they were picked', function () {
    $producer = ProfessionalRole::factory()->create(['name' => 'Producer', 'slug' => 'producer']);
    $copywriter = ProfessionalRole::factory()->create(['name' => 'Copywriter', 'slug' => 'copywriter']);
    $growth = ProfessionalRole::factory()->create(['name' => 'Head of Growth', 'slug' => 'head-of-growth']);

    fillApplication(Livewire::test('pages::freelancer.apply'), validApplication(['role_ids' => [(string) $producer->id, (string) $growth->id, (string) $copywriter->id]]))
        ->call('submit')
        ->assertHasNoErrors();

    $application = FreelancerApplication::firstOrFail();

    expect($application->roles)->toBe(['Producer', 'Head of Growth', 'Copywriter'])
        ->and($application->specialty)->toBe('Producer');
});

it('needs between one and three roles that exist', function () {
    $four = collect(['A One', 'B Two', 'C Three', 'D Four'])
        ->map(fn (string $name) => (string) ProfessionalRole::factory()->create(['name' => $name])->id)
        ->all();

    fillApplication(Livewire::test('pages::freelancer.apply'), validApplication(['role_ids' => []]))
        ->call('submit')
        ->assertHasErrors(['role_ids' => 'required']);

    fillApplication(Livewire::test('pages::freelancer.apply'), validApplication(['role_ids' => $four]))
        ->call('submit')
        ->assertHasErrors('role_ids');

    fillApplication(Livewire::test('pages::freelancer.apply'), validApplication(['role_ids' => ['9999']]))
        ->call('submit')
        ->assertHasErrors('role_ids.0');

    expect(FreelancerApplication::count())->toBe(0);
});

it('clears the state/province and city when another country is chosen, and the city when another state is', function () {
    Livewire::test('pages::freelancer.apply')
        ->set('country', 'Ukraine')
        ->set('state', 'Kyiv')
        ->set('city', Locations::cities('Ukraine', 'Kyiv')[0])
        ->set('state', 'Cherkaska')
        ->assertSet('state', 'Cherkaska')
        ->assertSet('city', '')
        ->set('city', 'Cherkasy')
        ->set('country', 'Poland')
        ->assertSet('state', '')
        ->assertSet('city', '');
});

it('offers only the states of the chosen country and the cities of the chosen state', function () {
    $component = Livewire::test('pages::freelancer.apply')
        ->set('country', 'Ukraine')
        ->assertSee('Cherkaska')
        ->assertDontSee('Andalusia')
        ->set('state', 'Cherkaska')
        ->assertSee('Cherkasy');

    expect($component->instance()->hasStates())->toBeTrue()
        ->and($component->instance()->hasCities())->toBeTrue();
});

it('requires a state/province and a city when the country and the state have them', function () {
    fillApplication(Livewire::test('pages::freelancer.apply'), validApplication(['state' => '']))
        ->call('submit')
        ->assertHasErrors(['state' => 'required']);

    fillApplication(Livewire::test('pages::freelancer.apply'), validApplication(['city' => '']))
        ->call('submit')
        ->assertHasErrors(['city' => 'required']);

    expect(FreelancerApplication::count())->toBe(0);
});

it('does not ask for a state/province or city when the country has none', function () {
    $component = Livewire::test('pages::freelancer.apply')->set('country', 'Gibraltar');

    expect($component->instance()->hasStates())->toBeFalse()
        ->and($component->instance()->hasCities())->toBeFalse();

    fillApplication($component, validApplication(['country' => 'Gibraltar', 'state' => '', 'city' => '']))
        ->call('submit')
        ->assertHasNoErrors();

    $application = FreelancerApplication::firstOrFail();

    expect($application->state)->toBeNull()
        ->and($application->city)->toBeNull()
        ->and($application->location)->toBe('Gibraltar');
});

it('does not ask for a city when the state/province has none', function () {
    $component = fillApplication(Livewire::test('pages::freelancer.apply'), validApplication(['country' => 'Spain', 'state' => 'Andalusia', 'city' => '']));

    expect($component->instance()->hasStates())->toBeTrue()
        ->and($component->instance()->hasCities())->toBeFalse();

    $component->call('submit')->assertHasNoErrors();

    $application = FreelancerApplication::firstOrFail();

    expect($application->state)->toBe('Andalusia')
        ->and($application->city)->toBeNull()
        ->and($application->location)->toBe('Andalusia, Spain');
});

it('rejects a location that is not in the list', function (array $location, string $errorKey) {
    fillApplication(Livewire::test('pages::freelancer.apply'), validApplication($location))
        ->call('submit')
        ->assertHasErrors($errorKey);

    expect(FreelancerApplication::count())->toBe(0);
})->with([
    'unknown country' => [['country' => 'Atlantis', 'state' => '', 'city' => ''], 'country'],
    'state of another country' => [['state' => 'Andalusia', 'city' => ''], 'state'],
    'city of another state' => [['city' => 'Cherkasy'], 'city'],
]);

it('rejects a portfolio link that is not a url', function () {
    fillApplication(Livewire::test('pages::freelancer.apply'), validApplication(['portfolio_links' => "https://ada.test\nnot a link"]))
        ->call('submit')
        ->assertHasErrors('portfolio_links');

    expect(FreelancerApplication::count())->toBe(0);
});

it('rejects an email that already has a pending application', function () {
    FreelancerApplication::factory()->create(['email' => 'ada@example.com']);

    fillApplication(Livewire::test('pages::freelancer.apply'), validApplication())
        ->call('submit')
        ->assertHasErrors('email');

    expect(FreelancerApplication::count())->toBe(1);
});

it('rejects an email that already belongs to an account', function () {
    User::factory()->create(['email' => 'ada@example.com']);

    fillApplication(Livewire::test('pages::freelancer.apply'), validApplication())
        ->call('submit')
        ->assertHasErrors('email');
});

it('lets an applicant whose application was rejected apply again', function () {
    FreelancerApplication::factory()->rejected()->create(['email' => 'ada@example.com']);

    fillApplication(Livewire::test('pages::freelancer.apply'), validApplication())
        ->call('submit')
        ->assertHasNoErrors();

    expect(FreelancerApplication::count())->toBe(2);
});

it('silently discards submissions that fill in the honeypot field', function () {
    fillApplication(Livewire::test('pages::freelancer.apply'), validApplication(['company_website' => 'https://spam.test']))
        ->call('submit')
        ->assertSet('submitted', true);

    expect(FreelancerApplication::count())->toBe(0);
});

it('throttles repeated submissions from the same address', function () {
    RateLimiter::clear('freelancer-application:127.0.0.1');

    foreach (range(1, 5) as $attempt) {
        fillApplication(Livewire::test('pages::freelancer.apply'), validApplication(['email' => "applicant{$attempt}@example.com"]))
            ->call('submit')
            ->assertHasNoErrors();
    }

    fillApplication(Livewire::test('pages::freelancer.apply'), validApplication(['email' => 'applicant6@example.com']))
        ->call('submit')
        ->assertHasErrors('email');

    expect(FreelancerApplication::count())->toBe(5);
});
