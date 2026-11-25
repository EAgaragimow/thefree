<?php

use App\Actions\Directory\DirectoryQuery;
use App\Enums\WeeklyHours;
use App\Models\FreelancerProfile;
use App\Models\FreelancerProfileRevision;
use App\Models\Industry;
use App\Models\ProfessionalFunction;
use App\Models\ProfessionalRole;
use App\Models\Service;
use App\Models\Skill;
use App\Models\Tool;
use App\Models\User;
use Livewire\Livewire;

function directoryNames(array $filters = [], string $sort = DirectoryQuery::SORT_BEST): array
{
    return app(DirectoryQuery::class)
        ->paginate($filters, $sort)
        ->getCollection()
        ->map(fn (FreelancerProfile $profile): string => $profile->user->name)
        ->all();
}

it('lists only visible profiles', function () {
    directoryProfile(user: ['name' => 'Visible']);
    directoryProfile(['published_at' => null], ['name' => 'Never published']);
    directoryProfile(user: ['name' => 'Suspended', 'status' => 'suspended']);
    directoryProfile(user: ['name' => 'Deactivated', 'deactivated_at' => now()]);

    expect(directoryNames())->toBe(['Visible']);
});

it('shows live content, not edits that wait for review', function () {
    $profile = directoryProfile(['professional_title' => 'Live title']);
    $revision = FreelancerProfileRevision::factory()->pendingReview()->create(['freelancer_profile_id' => $profile->id]);
    $revision->update(['payload' => $revision->content()->with(['professional_title' => 'Pending title'])->toArray()]);

    $page = app(DirectoryQuery::class)->paginate([]);

    expect($page->first()->professional_title)->toBe('Live title');
});

it('searches name, title, about text, skills and services', function (string $term, string $expected) {
    $ada = directoryProfile(['professional_title' => 'Backend engineer', 'about' => 'I like compilers.'], ['name' => 'Ada Lovelace']);
    $ada->skills()->attach(Skill::factory()->create(['name' => 'Rust']));
    Service::factory()->create(['freelancer_profile_id' => $ada->id, 'title' => 'Code review']);

    directoryProfile(['professional_title' => 'Illustrator', 'about' => 'Posters and covers.'], ['name' => 'Grace Hopper']);

    expect(directoryNames(['search' => $term]))->toBe([$expected]);
})->with([
    'name' => ['lovelace', 'Ada Lovelace'],
    'title' => ['engineer', 'Ada Lovelace'],
    'about' => ['compilers', 'Ada Lovelace'],
    'skill' => ['rust', 'Ada Lovelace'],
    'service' => ['code review', 'Ada Lovelace'],
    'other freelancer' => ['posters', 'Grace Hopper'],
]);

it('needs every word of a search to match somewhere', function () {
    $ada = directoryProfile(['professional_title' => 'Backend engineer'], ['name' => 'Ada Lovelace']);
    $ada->skills()->attach(Skill::factory()->create(['name' => 'Rust']));
    directoryProfile(['professional_title' => 'Backend engineer'], ['name' => 'Grace Hopper']);

    expect(directoryNames(['search' => 'backend rust']))->toBe(['Ada Lovelace'])
        ->and(directoryNames(['search' => 'backend']))->toHaveCount(2)
        ->and(directoryNames(['search' => 'backend cobol']))->toBe([]);
});

it('treats % and _ in a search as plain characters', function () {
    directoryProfile(['about' => 'We grew revenue by 100% last year.'], ['name' => 'Percent']);
    directoryProfile(['about' => 'We grew revenue a lot.'], ['name' => 'Plain']);

    expect(directoryNames(['search' => '100%']))->toBe(['Percent'])
        ->and(directoryNames(['search' => '%']))->toBe(['Percent'])
        ->and(directoryNames(['search' => '_']))->toBe([]);
});

it('filters by function', function () {
    $design = ProfessionalFunction::factory()->create();
    directoryProfile(['professional_function_id' => $design->id], ['name' => 'Designer']);
    directoryProfile(['professional_function_id' => ProfessionalFunction::factory()->create()->id], ['name' => 'Developer']);

    expect(directoryNames(['function_id' => (string) $design->id]))->toBe(['Designer']);
});

it('filters by any of the roles, whichever place they hold', function () {
    [$one, $two, $other] = ProfessionalRole::factory()->count(3)->create();
    directoryProfile(['main_role_id' => $one->id], ['name' => 'Main']);
    directoryProfile(['main_role_id' => $other->id, 'secondary_role_id' => $two->id], ['name' => 'Secondary']);
    directoryProfile(['main_role_id' => $other->id, 'third_role_id' => $one->id], ['name' => 'Third']);
    directoryProfile(['main_role_id' => $other->id], ['name' => 'Unrelated']);

    expect(directoryNames(['role_ids' => [(string) $one->id, (string) $two->id]], DirectoryQuery::SORT_NEWEST))
        ->toEqualCanonicalizing(['Main', 'Secondary', 'Third']);
});

it('filters by any selected skill, tool or industry', function (string $relation, string $filter, string $model) {
    [$wanted, $second, $unused] = $model::factory()->count(3)->create();
    directoryProfile(user: ['name' => 'Has wanted'])->{$relation}()->attach($wanted);
    directoryProfile(user: ['name' => 'Has second'])->{$relation}()->attach($second);
    directoryProfile(user: ['name' => 'Has unused'])->{$relation}()->attach($unused);
    directoryProfile(user: ['name' => 'Has nothing']);

    expect(directoryNames([$filter => [(string) $wanted->id, (string) $second->id]]))
        ->toEqualCanonicalizing(['Has wanted', 'Has second']);
})->with([
    'skills' => ['skills', 'skill_ids', Skill::class],
    'tools' => ['tools', 'tool_ids', Tool::class],
    'industries' => ['industries', 'industry_ids', Industry::class],
]);

it('filters by hourly rate in dollars', function () {
    directoryProfile(['hourly_rate_cents' => 3000], ['name' => 'Cheap']);
    directoryProfile(['hourly_rate_cents' => 6000], ['name' => 'Mid']);
    directoryProfile(['hourly_rate_cents' => 12000], ['name' => 'Pricey']);

    expect(directoryNames(['rate_min' => '50', 'rate_max' => '100']))->toBe(['Mid'])
        ->and(directoryNames(['rate_min' => '60']))->toEqualCanonicalizing(['Mid', 'Pricey'])
        ->and(directoryNames(['rate_max' => '60']))->toEqualCanonicalizing(['Cheap', 'Mid']);
});

it('filters by weekly hours', function () {
    directoryProfile(['weekly_hours' => WeeklyHours::UpTo10], ['name' => 'Part time']);
    directoryProfile(['weekly_hours' => WeeklyHours::Over40], ['name' => 'Full time']);

    expect(directoryNames(['weekly_hours' => '41+']))->toBe(['Full time'])
        ->and(directoryNames(['weekly_hours' => 'nonsense']))->toHaveCount(2);
});

it('has no location or time zone filter', function () {
    directoryProfile(['location' => 'Lisbon, Portugal', 'timezone' => 'Europe/Lisbon'], ['name' => 'Lisbon']);
    directoryProfile(['location' => 'Austin, United States', 'timezone' => 'America/Chicago'], ['name' => 'Austin']);

    expect(directoryNames(['location' => 'portugal', 'timezone' => 'America/Chicago']))->toHaveCount(2);

    Livewire::actingAs(User::factory()->client()->paid()->create())
        ->test('pages::directory.index')
        ->assertDontSee('Location')
        ->assertDontSee('Time zone');
});

it('puts newbies first, then orders by score, then by newest', function () {
    directoryProfile(['score' => 90, 'created_at' => now()->subMonths(6)], ['name' => 'Veteran star']);
    directoryProfile(['score' => 10, 'is_newbie' => true, 'created_at' => now()->subDays(20)], ['name' => 'Older newbie']);
    directoryProfile(['score' => 10, 'is_newbie' => true, 'created_at' => now()->subDays(2)], ['name' => 'Newest newbie']);
    directoryProfile(['score' => 50, 'is_newbie' => true, 'created_at' => now()->subDays(10)], ['name' => 'Strong newbie']);
    directoryProfile(['score' => 90, 'created_at' => now()->subMonths(2)], ['name' => 'Veteran tie, older']);
    directoryProfile(['score' => 90, 'created_at' => now()->subMonths(1)], ['name' => 'Veteran tie, newer']);

    expect(directoryNames())->toBe([
        'Strong newbie',
        'Newest newbie',
        'Older newbie',
        'Veteran tie, newer',
        'Veteran tie, older',
        'Veteran star',
    ]);
});

it('lets a name match beat the newbie boost', function () {
    directoryProfile(['is_newbie' => true, 'about' => 'Unrelated.'], ['name' => 'Someone new']);
    directoryProfile(['score' => 0, 'about' => 'Unrelated.'], ['name' => 'Sam Smith']);
    directoryProfile(['is_newbie' => true, 'about' => 'Knows Sam well.'], ['name' => 'Newbie Kim']);

    expect(directoryNames(['search' => 'sam']))->toBe(['Sam Smith', 'Newbie Kim']);
});

it('keeps the newbie boost out of the other sort orders', function () {
    directoryProfile(['hourly_rate_cents' => 9000, 'is_newbie' => true], ['name' => 'Newbie']);
    directoryProfile(['hourly_rate_cents' => 3000], ['name' => 'Cheap veteran']);

    expect(directoryNames([], DirectoryQuery::SORT_RATE_ASC))->toBe(['Cheap veteran', 'Newbie']);
});

it('sorts by rate, newest and score', function () {
    directoryProfile(['hourly_rate_cents' => 9000, 'score' => 10, 'published_at' => now()->subDays(3)], ['name' => 'Mid']);
    directoryProfile(['hourly_rate_cents' => 3000, 'score' => 50, 'published_at' => now()->subDays(2)], ['name' => 'Cheap']);
    directoryProfile(['hourly_rate_cents' => 15000, 'score' => 30, 'published_at' => now()->subDay()], ['name' => 'Pricey']);

    expect(directoryNames([], DirectoryQuery::SORT_RATE_ASC))->toBe(['Cheap', 'Mid', 'Pricey'])
        ->and(directoryNames([], DirectoryQuery::SORT_RATE_DESC))->toBe(['Pricey', 'Mid', 'Cheap'])
        ->and(directoryNames([], DirectoryQuery::SORT_NEWEST))->toBe(['Pricey', 'Cheap', 'Mid'])
        ->and(directoryNames([], DirectoryQuery::SORT_BEST))->toBe(['Cheap', 'Pricey', 'Mid']);
});

it('ranks name and title matches above other matches for a search', function () {
    directoryProfile(['about' => 'Works with Sam often.', 'score' => 90], ['name' => 'High score']);
    directoryProfile(['professional_title' => 'Sam the designer', 'score' => 5], ['name' => 'Title match']);
    directoryProfile(['score' => 1], ['name' => 'Sam Smith']);

    expect(directoryNames(['search' => 'sam']))->toBe(['Sam Smith', 'Title match', 'High score']);
});

it('lists featured profiles apart from the regular list, in the chosen order', function () {
    directoryProfile(user: ['name' => 'Second featured'], attributes: ['featured_at' => now(), 'featured_sort' => 2]);
    directoryProfile(user: ['name' => 'First featured'], attributes: ['featured_at' => now(), 'featured_sort' => 1]);
    directoryProfile(user: ['name' => 'Regular']);

    $query = app(DirectoryQuery::class);

    expect($query->featured([])->map(fn ($profile) => $profile->user->name)->all())->toBe(['First featured', 'Second featured'])
        ->and(directoryNames())->toBe(['Regular']);
});

it('applies filters to the featured block too', function () {
    directoryProfile(['featured_at' => now(), 'featured_sort' => 1, 'professional_title' => 'Rome guide'], ['name' => 'Roman']);
    directoryProfile(['featured_at' => now(), 'featured_sort' => 2, 'professional_title' => 'Oslo guide'], ['name' => 'Norwegian']);

    $names = app(DirectoryQuery::class)->featured(['search' => 'rome'])->map(fn ($profile) => $profile->user->name)->all();

    expect($names)->toBe(['Roman']);
});

it('does not feature a hidden profile', function () {
    directoryProfile(['featured_at' => now(), 'featured_sort' => 1], ['name' => 'Suspended', 'status' => 'suspended']);

    expect(app(DirectoryQuery::class)->featured([]))->toHaveCount(0);
});

it('pages the list', function () {
    FreelancerProfile::factory()->published()->count(DirectoryQuery::PER_PAGE + 1)->create();

    $page = app(DirectoryQuery::class)->paginate([]);

    expect($page->total())->toBe(DirectoryQuery::PER_PAGE + 1)
        ->and($page->count())->toBe(DirectoryQuery::PER_PAGE)
        ->and($page->lastPage())->toBe(2);
});

it('wires the page filters to the directory query', function () {
    directoryProfile(['weekly_hours' => WeeklyHours::UpTo10], ['name' => 'Lisbon freelancer']);
    directoryProfile(['weekly_hours' => WeeklyHours::Over40], ['name' => 'Austin freelancer']);

    Livewire::actingAs(User::factory()->client()->paid()->create())
        ->test('pages::directory.index')
        ->assertSee('Lisbon freelancer')
        ->assertSee('Austin freelancer')
        ->set('weekly_hours', '1-10')
        ->assertSee('Lisbon freelancer')
        ->assertDontSee('Austin freelancer')
        ->call('clearFilters')
        ->assertSee('Austin freelancer')
        ->set('search', 'nobody-has-this')
        ->assertSee('No freelancers found');
});

it('shows featured freelancers in their own block on the first page', function () {
    directoryProfile(['featured_at' => now(), 'featured_sort' => 1], ['name' => 'Starred']);
    directoryProfile(user: ['name' => 'Ordinary']);

    Livewire::actingAs(User::factory()->client()->paid()->create())
        ->test('pages::directory.index')
        ->assertSeeInOrder(['Featured', 'Starred', 'All freelancers', 'Ordinary']);
});
