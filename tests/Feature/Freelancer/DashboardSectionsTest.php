<?php

use App\Actions\Profiles\OpenProfileRevision;
use App\Enums\ProfileStatus;
use App\Models\FreelancerProfile;
use App\Models\FreelancerProfileRevision;
use Livewire\Livewire;

/**
 * @param  list<array<string, mixed>>  $experiences
 */
function profileWithWorkHistory(array $experiences = [], bool $published = true): FreelancerProfile
{
    $profile = $published ? FreelancerProfile::factory()->published()->create() : FreelancerProfile::factory()->create();

    if ($experiences !== []) {
        $revision = app(OpenProfileRevision::class)($profile);
        $revision->forceFill(['payload' => $revision->content()->with(['experiences' => $experiences])->toArray()])->save();
    }

    return $profile->refresh();
}

/**
 * @return list<array<string, mixed>>
 */
function positions(int $count): array
{
    return array_map(fn (int $number): array => [
        'company' => sprintf('Company %02d', $number),
        'role' => 'Designer',
        'started_at' => '2020-01-01',
        'ended_at' => null,
        'description' => null,
    ], range(1, $count));
}

// Work history -------------------------------------------------------------------------------

it('adds a position to a draft revision and leaves the live profile alone', function () {
    $profile = profileWithWorkHistory();

    Livewire::actingAs($profile->user)
        ->test('freelancer.work-history-section')
        ->call('startCreating')
        ->set('company', 'Northwind Studio')
        ->set('role', 'Growth Marketer')
        ->set('started_at', '2021-03-01')
        ->set('description', 'Ran paid channels.')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('profile-content-saved')
        ->assertSee('Growth Marketer · Northwind Studio');

    $profile->refresh();

    expect($profile->openRevision->content()->experiences())->toHaveCount(1)
        ->and($profile->openRevision->content()->experiences()[0])->toMatchArray([
            'company' => 'Northwind Studio',
            'role' => 'Growth Marketer',
            'started_at' => '2021-03-01',
            'ended_at' => null,
            'description' => 'Ran paid channels.',
        ])
        ->and($profile->experiences()->count())->toBe(0);
});

it('edits a position', function () {
    $profile = profileWithWorkHistory(positions(2));

    Livewire::actingAs($profile->user)
        ->test('freelancer.work-history-section')
        ->call('startEditing', 1)
        ->assertSet('company', 'Company 02')
        ->set('company', 'Renamed')
        ->set('ended_at', '2022-06-30')
        ->call('save')
        ->assertHasNoErrors();

    $experiences = $profile->refresh()->openRevision->content()->experiences();

    expect(array_column($experiences, 'company'))->toBe(['Company 01', 'Renamed'])
        ->and($experiences[1]['ended_at'])->toBe('2022-06-30');
});

it('removes a position', function () {
    $profile = profileWithWorkHistory(positions(3));

    Livewire::actingAs($profile->user)
        ->test('freelancer.work-history-section')
        ->call('delete', 0)
        ->assertHasNoErrors();

    expect(array_column($profile->refresh()->openRevision->content()->experiences(), 'company'))->toBe(['Company 02', 'Company 03']);
});

it('cannot edit or remove a position that does not exist', function () {
    $profile = profileWithWorkHistory(positions(1));

    Livewire::actingAs($profile->user)
        ->test('freelancer.work-history-section')
        ->call('startEditing', 5)
        ->assertNotFound();

    Livewire::actingAs($profile->user)
        ->test('freelancer.work-history-section')
        ->call('delete', 5)
        ->assertNotFound();
});

it('validates a position', function (array $values, array $errors) {
    $profile = profileWithWorkHistory();

    $component = Livewire::actingAs($profile->user)
        ->test('freelancer.work-history-section')
        ->call('startCreating');

    foreach ($values as $field => $value) {
        $component->set($field, $value);
    }

    $component->call('save')->assertHasErrors($errors);

    expect($profile->refresh()->openRevision)->toBeNull();
})->with([
    'nothing' => [[], ['company', 'role', 'started_at']],
    'invalid start date' => [['company' => 'A', 'role' => 'B', 'started_at' => 'soon'], ['started_at']],
    'ends before it starts' => [['company' => 'A', 'role' => 'B', 'started_at' => '2022-01-01', 'ended_at' => '2021-01-01'], ['ended_at']],
    'description too long' => [['company' => 'A', 'role' => 'B', 'started_at' => '2022-01-01', 'description' => str_repeat('x', 2001)], ['description']],
]);

it('limits the work history to twenty positions', function () {
    $profile = profileWithWorkHistory(positions(20));

    Livewire::actingAs($profile->user)
        ->test('freelancer.work-history-section')
        ->assertDontSeeHtml('wire:click="startCreating"')
        ->call('startCreating')
        ->set('company', 'One too many')
        ->set('role', 'Designer')
        ->set('started_at', '2020-01-01')
        ->call('save')
        ->assertHasErrors('company');

    expect($profile->refresh()->openRevision->content()->experiences())->toHaveCount(20);
});

it('refuses to change the work history while the profile is under review', function () {
    $profile = profileWithWorkHistory(positions(1));
    $profile->openRevision->forceFill(['status' => ProfileStatus::PendingReview])->save();

    Livewire::actingAs($profile->user)
        ->test('freelancer.work-history-section')
        ->assertDontSeeHtml('wire:click="startCreating"')
        ->call('startCreating')
        ->set('company', 'Sneaky')
        ->set('role', 'Designer')
        ->set('started_at', '2020-01-01')
        ->call('save')
        ->assertHasErrors('company');

    expect($profile->refresh()->openRevision->content()->experiences())->toHaveCount(1);
});

it('says so when there is no work history', function () {
    $profile = profileWithWorkHistory();

    Livewire::actingAs($profile->user)
        ->test('freelancer.work-history-section')
        ->assertSee('No work history yet.');
});

it('keeps the work history of other freelancers out of the section', function () {
    $other = profileWithWorkHistory(positions(1));
    $mine = profileWithWorkHistory();

    Livewire::actingAs($mine->user)
        ->test('freelancer.work-history-section')
        ->assertDontSee('Company 01');

    expect($other->openRevision->content()->experiences())->toHaveCount(1);
});

// Pagination ---------------------------------------------------------------------------------

it('pages the work history twelve at a time without touching the address bar', function () {
    $profile = profileWithWorkHistory(positions(13));

    $component = Livewire::actingAs($profile->user)
        ->test('freelancer.work-history-section')
        ->assertSee('Company 01')
        ->assertSee('Company 12')
        ->assertDontSee('Company 13');

    $component->call('nextPage')
        ->assertSee('Company 13')
        ->assertDontSee('Company 12')
        ->assertSet('paginators.page', 2);

    expect(array_key_exists('page', $component->effects['url'] ?? []))->toBeFalse();
});

it('does not remember the page: a new visit starts on the first page again', function () {
    $profile = profileWithWorkHistory(positions(13));

    Livewire::actingAs($profile->user)->test('freelancer.work-history-section')->call('nextPage');

    Livewire::actingAs($profile->user)
        ->test('freelancer.work-history-section')
        ->assertSee('Company 01')
        ->assertDontSee('Company 13');
});

it('moves back a page when the last entry of the last page is removed', function () {
    $profile = profileWithWorkHistory(positions(13));

    Livewire::actingAs($profile->user)
        ->test('freelancer.work-history-section')
        ->call('nextPage')
        ->call('delete', 12)
        ->assertSee('Company 12')
        ->assertDontSee('Company 13');
});

// Revision panel -----------------------------------------------------------------------------

it('shows the submit and discard panel at the bottom when there are unsubmitted changes', function () {
    $profile = profileWithWorkHistory(positions(1));

    Livewire::actingAs($profile->user)
        ->test('profile-revision-bar')
        ->assertSeeHtml('fixed inset-x-0 bottom-0')
        ->assertSee('Submit for review')
        ->assertSee('Discard changes');
});

it('shows no panel when there is nothing to submit', function () {
    $profile = profileWithWorkHistory();

    Livewire::actingAs($profile->user)
        ->test('profile-revision-bar')
        ->assertDontSeeHtml('fixed inset-x-0')
        ->assertDontSee('Submit for review');
});

it('discards the changes from the panel and then goes away', function () {
    $profile = profileWithWorkHistory(positions(1));

    Livewire::actingAs($profile->user)
        ->test('profile-revision-bar')
        ->call('discard')
        ->assertSee('Your changes have been discarded.')
        ->assertDispatched('profile-revision-changed')
        ->call('dismissNotice')
        ->assertDontSeeHtml('fixed inset-x-0');

    expect(FreelancerProfileRevision::query()->where('freelancer_profile_id', $profile->id)->exists())->toBeFalse();
});

it('puts the panel on the dashboard only when the profile exists', function () {
    $profile = profileWithWorkHistory(positions(1));

    $this->actingAs($profile->user)->get(route('freelancer.dashboard'))
        ->assertOk()
        ->assertSeeHtml('fixed inset-x-0 bottom-0');
});
