<?php

use App\Actions\Profiles\SetProfileFeatured;
use App\Filament\Resources\FreelancerProfiles\Pages\ListFreelancerProfiles;
use App\Filament\Resources\FreelancerProfiles\Pages\ViewFreelancerProfile;
use App\Models\AuditLog;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

it('features a published profile with a reason and audits it', function () {
    $profile = directoryProfile();

    Livewire::test(ViewFreelancerProfile::class, ['record' => $profile->getKey()])
        ->callAction('feature', data: ['position' => 3, 'reason' => 'Great portfolio'])
        ->assertNotified('Profile featured.');

    $profile->refresh();

    expect($profile->isFeatured())->toBeTrue()
        ->and($profile->featured_sort)->toBe(3);

    $log = AuditLog::query()->where('action', 'profile.featured')->first();

    expect($log->actor_id)->toBe($this->admin->id)
        ->and($log->subject_id)->toBe($profile->id)
        ->and($log->reason)->toBe('Great portfolio')
        ->and($log->metadata['position'])->toBe(3);
});

it('puts a profile without a position at the end of the featured block', function () {
    directoryProfile(['featured_at' => now(), 'featured_sort' => 4]);
    $profile = directoryProfile();

    Livewire::test(ViewFreelancerProfile::class, ['record' => $profile->getKey()])
        ->callAction('feature', data: ['reason' => 'Fresh work']);

    expect($profile->refresh()->featured_sort)->toBe(5);
});

it('unfeatures a profile with a reason and audits it', function () {
    $profile = directoryProfile(['featured_at' => now(), 'featured_sort' => 1]);

    Livewire::test(ViewFreelancerProfile::class, ['record' => $profile->getKey()])
        ->callAction('unfeature', data: ['reason' => 'Rotation ended']);

    expect($profile->refresh()->isFeatured())->toBeFalse()
        ->and($profile->featured_sort)->toBeNull();

    expect(AuditLog::query()->where('action', 'profile.unfeatured')->where('reason', 'Rotation ended')->exists())->toBeTrue();
});

it('requires a reason', function () {
    $profile = directoryProfile();

    Livewire::test(ViewFreelancerProfile::class, ['record' => $profile->getKey()])
        ->callAction('feature', data: ['reason' => ''])
        ->assertHasActionErrors(['reason' => 'required']);

    expect($profile->refresh()->isFeatured())->toBeFalse()
        ->and(fn () => app(SetProfileFeatured::class)($profile, true, $this->admin, '   '))->toThrow(ValidationException::class);
});

it('offers Feature only for published profiles and Unfeature only for featured ones', function () {
    $published = directoryProfile();
    $unpublished = directoryProfile(['published_at' => null]);
    $featured = directoryProfile(['featured_at' => now(), 'featured_sort' => 1]);

    Livewire::test(ListFreelancerProfiles::class)
        ->assertActionVisible(TestAction::make('feature')->table($published))
        ->assertActionHidden(TestAction::make('unfeature')->table($published))
        ->assertActionHidden(TestAction::make('feature')->table($unpublished))
        ->assertActionHidden(TestAction::make('feature')->table($featured))
        ->assertActionVisible(TestAction::make('unfeature')->table($featured));
});

it('refuses to feature an unpublished profile', function () {
    $profile = directoryProfile(['published_at' => null]);

    expect(fn () => app(SetProfileFeatured::class)($profile, true, $this->admin, 'Please'))
        ->toThrow(DomainException::class, 'Only published profiles can be featured.');
});

it('refuses to repeat a state', function () {
    $profile = directoryProfile();

    expect(fn () => app(SetProfileFeatured::class)($profile, false, $this->admin, 'Please'))
        ->toThrow(DomainException::class, 'The profile is not featured.');

    app(SetProfileFeatured::class)($profile, true, $this->admin, 'Please');

    expect(fn () => app(SetProfileFeatured::class)($profile, true, $this->admin, 'Please'))
        ->toThrow(DomainException::class, 'The profile is already featured.');
});

it('shows a featured profile in the directory and a profile that was unfeatured in the list', function () {
    $profile = directoryProfile(user: ['name' => 'Star']);
    app(SetProfileFeatured::class)($profile, true, $this->admin, 'Great work');

    Livewire::actingAs(User::factory()->client()->paid()->create())
        ->test('pages::directory.index')
        ->assertSeeInOrder(['Featured', 'Star']);

    app(SetProfileFeatured::class)($profile, false, $this->admin, 'Rotation');

    Livewire::actingAs(User::factory()->client()->paid()->create())
        ->test('pages::directory.index')
        ->assertDontSee('All freelancers')
        ->assertSee('Star')
        ->assertDontSee('Featured');
});
