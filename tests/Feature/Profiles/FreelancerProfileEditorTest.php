<?php

use App\Actions\Profiles\ApproveProfile;
use App\Enums\ProfileStatus;
use App\Models\Category;
use App\Models\FreelancerProfile;
use App\Models\FreelancerProfileRevision;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * @return array{0: User, 1: FreelancerProfile}
 */
function freelancerWithProfile(string $state = 'draft'): array
{
    $user = User::factory()->freelancer()->create();
    $profile = FreelancerProfile::factory()->create(['user_id' => $user->id]);

    if ($state === 'published') {
        $profile->forceFill(['published_at' => now()])->save();
    } else {
        FreelancerProfileRevision::factory()->{$state}()->create(['freelancer_profile_id' => $profile->id]);
    }

    return [$user, $profile->load('openRevision')];
}

function completeProfile(FreelancerProfile $profile): void
{
    $revision = $profile->openRevision;
    $revision->forceFill(['payload' => $revision->content()->with([
        'about' => str_repeat('I help brands grow. ', 4),
        'location' => 'Kyiv, Ukraine',
        'professional_title' => 'Designer',
        'category_ids' => [Category::factory()->create()->id],
        'skill_ids' => [Skill::factory()->create()->id],
    ])->toArray()])->save();
}

it('opens the editor prepopulated with the open revision', function () {
    [$user, $profile] = freelancerWithProfile();
    $profile->openRevision->forceFill(['payload' => $profile->openRevision->content()->with(['professional_title' => 'Brand designer'])->toArray()])->save();

    Livewire::actingAs($user)
        ->test('pages::freelancer.profile')
        ->assertSet('professional_title', 'Brand designer');
});

it('opens the editor from the live profile when nothing is being edited', function () {
    [$user, $profile] = freelancerWithProfile('published');
    $profile->update(['professional_title' => 'Live title']);

    Livewire::actingAs($user)
        ->test('pages::freelancer.profile')
        ->assertSet('professional_title', 'Live title');
});

it('returns 404 over HTTP for a freelancer who has no profile', function () {
    $this->actingAs(User::factory()->freelancer()->create())
        ->get(route('freelancer.profile'))
        ->assertNotFound();
});

it('saves the draft with categories, skills and work history in the revision', function () {
    [$user, $profile] = freelancerWithProfile();
    $category = Category::factory()->create();
    $skill = Skill::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::freelancer.profile')
        ->set('professional_title', 'Product designer')
        ->set('about', 'About me')
        ->set('location', 'Lviv, Ukraine')
        ->set('linkedin', 'https://linkedin.com/in/ada')
        ->set('category_ids', [(string) $category->id])
        ->set('skill_ids', [(string) $skill->id])
        ->call('addExperience')
        ->set('experiences.0.company', 'Acme')
        ->set('experiences.0.role', 'Lead designer')
        ->set('experiences.0.started_at', '2020-01-01')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('notice', 'Your changes have been saved.');

    $profile->refresh();
    $content = $profile->openRevision->content();

    expect($content->get('professional_title'))->toBe('Product designer')
        ->and($content->get('social_links'))->toBe(['linkedin' => 'https://linkedin.com/in/ada'])
        ->and($content->categoryIds())->toBe([$category->id])
        ->and($content->skillIds())->toBe([$skill->id])
        ->and(array_column($content->experiences(), 'company'))->toBe(['Acme'])
        ->and($profile->openRevision->status)->toBe(ProfileStatus::Draft)
        ->and($profile->isPublished())->toBeFalse()
        ->and($profile->categories()->count())->toBe(0);
});

it('removes a work history entry', function () {
    [$user, $profile] = freelancerWithProfile();

    $component = Livewire::actingAs($user)
        ->test('pages::freelancer.profile')
        ->call('addExperience')
        ->set('experiences.0.company', 'Acme')
        ->set('experiences.0.role', 'Designer')
        ->set('experiences.0.started_at', '2020-01-01')
        ->call('save');

    $component->call('removeExperience', 0)->call('save')->assertHasNoErrors();

    expect($profile->refresh()->openRevision->content()->experiences())->toBe([]);
});

it('validates work history entries', function () {
    [$user] = freelancerWithProfile();

    Livewire::actingAs($user)
        ->test('pages::freelancer.profile')
        ->call('addExperience')
        ->call('save')
        ->assertHasErrors(['experiences.0.company', 'experiences.0.role', 'experiences.0.started_at']);
});

it('rejects an invalid website', function () {
    [$user] = freelancerWithProfile();

    Livewire::actingAs($user)
        ->test('pages::freelancer.profile')
        ->set('website', 'not-a-url')
        ->call('save')
        ->assertHasErrors('website');
});

it('submits a complete profile for review', function () {
    [$user, $profile] = freelancerWithProfile();
    completeProfile($profile);

    Livewire::actingAs($user)
        ->test('pages::freelancer.profile')
        ->call('submitForReview')
        ->assertHasNoErrors();

    expect($profile->refresh()->openRevision->status)->toBe(ProfileStatus::PendingReview)
        ->and($profile->openRevision->submitted_at)->not->toBeNull();
});

it('refuses to submit an incomplete profile and says what is missing', function () {
    [$user, $profile] = freelancerWithProfile();

    Livewire::actingAs($user)
        ->test('pages::freelancer.profile')
        ->set('professional_title', '')
        ->set('about', 'short')
        ->set('location', '')
        ->set('category_ids', [])
        ->set('skill_ids', [])
        ->call('submitForReview')
        ->assertHasErrors(['professional_title', 'about', 'location', 'category_ids', 'skill_ids']);

    expect($profile->refresh()->openRevision->status)->toBe(ProfileStatus::Draft);
});

it('tells the freelancer that the profile is locked while under review', function () {
    [$user] = freelancerWithProfile('pendingReview');

    Livewire::actingAs($user)
        ->test('pages::freelancer.profile')
        ->assertSee('Your profile is under review')
        ->assertDontSee('Submit for review');
});

it('shows the changes an administrator requested', function () {
    [$user] = freelancerWithProfile('changesRequested');

    Livewire::actingAs($user)
        ->test('pages::freelancer.profile')
        ->assertSee('An administrator requested changes')
        ->assertSee('Please expand your About section.');
});

it('keeps serving the approved version while a published profile is being edited', function () {
    [$user, $profile] = freelancerWithProfile('published');
    $profile->update(['professional_title' => 'Approved title']);

    Livewire::actingAs($user)
        ->test('pages::freelancer.profile')
        ->set('professional_title', 'A brand new title')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Your last approved version is still live');

    $profile->refresh();

    expect($profile->displayStatus())->toBe(ProfileStatus::Draft)
        ->and($profile->professional_title)->toBe('Approved title')
        ->and($profile->openRevision->content()->get('professional_title'))->toBe('A brand new title')
        ->and($profile->isPublished())->toBeTrue();
});

it('goes back to published when edits are reverted', function () {
    [$user, $profile] = freelancerWithProfile('published');
    $original = $profile->professional_title;

    $component = Livewire::actingAs($user)
        ->test('pages::freelancer.profile')
        ->set('professional_title', 'Temporary')
        ->call('save');

    expect($profile->refresh()->displayStatus())->toBe(ProfileStatus::Draft);

    $component->set('professional_title', $original)->call('save');

    expect($profile->refresh()->displayStatus())->toBe(ProfileStatus::Published);
});

it('lets a freelancer discard pending edits of a published profile', function () {
    [$user, $profile] = freelancerWithProfile('published');
    $original = $profile->professional_title;

    Livewire::actingAs($user)
        ->test('pages::freelancer.profile')
        ->set('professional_title', 'Temporary')
        ->call('save')
        ->call('discard')
        ->assertHasNoErrors()
        ->assertSet('professional_title', $original)
        ->assertSet('notice', 'Your changes have been discarded.');

    expect($profile->refresh()->openRevision)->toBeNull();
});

it('does not offer discarding before the first publication', function () {
    [$user] = freelancerWithProfile();

    Livewire::actingAs($user)
        ->test('pages::freelancer.profile')
        ->assertDontSee('Discard changes')
        ->call('discard')
        ->assertHasErrors('discard');
});

it('keeps the live photo when a new one is uploaded and swaps it on approval', function () {
    Notification::fake();
    Storage::fake('public');
    [$user, $profile] = freelancerWithProfile('published');
    Storage::disk('public')->put('freelancer-photos/live.jpg', 'live');
    $profile->update(['photo_path' => 'freelancer-photos/live.jpg']);
    $profile->update([
        'about' => str_repeat('Live about section. ', 4),
        'location' => 'Kyiv',
        'professional_title' => 'Designer',
    ]);
    $profile->categories()->sync([Category::factory()->create()->id]);
    $profile->skills()->sync([Skill::factory()->create()->id]);

    Livewire::actingAs($user)
        ->test('pages::freelancer.profile')
        ->set('photo', UploadedFile::fake()->image('new.jpg'))
        ->call('submitForReview')
        ->assertHasNoErrors();

    Storage::disk('public')->assertExists('freelancer-photos/live.jpg');
    $profile->refresh();
    expect($profile->photo_path)->toBe('freelancer-photos/live.jpg');

    app(ApproveProfile::class)($profile->openRevision, User::factory()->admin()->create());

    Storage::disk('public')->assertMissing('freelancer-photos/live.jpg');
    expect($profile->refresh()->photo_path)->not->toBe('freelancer-photos/live.jpg');
    Storage::disk('public')->assertExists($profile->photo_path);
});

it('does not let a freelancer open another freelancers profile', function () {
    [, $otherProfile] = freelancerWithProfile();
    [$user] = freelancerWithProfile();

    $component = Livewire::actingAs($user)->test('pages::freelancer.profile');

    expect($component->get('profileId'))->not->toBe($otherProfile->id);
});
