<?php

use App\Actions\Profiles\ApproveProfile;
use App\Enums\ProfileStatus;
use App\Models\FreelancerProfile;
use App\Models\FreelancerProfileRevision;
use App\Models\Industry;
use App\Models\ProfessionalFunction;
use App\Models\ProfessionalRole;
use App\Models\Skill;
use App\Models\Tool;
use App\Models\User;
use Database\Factories\FreelancerProfileRevisionFactory;
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
        ...FreelancerProfileRevisionFactory::completeDetails(),
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

it('saves the draft with its details and work history in the revision', function () {
    [$user, $profile] = freelancerWithProfile();
    $function = ProfessionalFunction::factory()->create();
    [$main, $secondary] = ProfessionalRole::factory()->count(2)->create();
    $skills = Skill::factory()->count(3)->create();
    $tools = Tool::factory()->count(2)->create();
    $industry = Industry::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::freelancer.profile')
        ->set('professional_title', 'Product designer')
        ->set('about', 'About me')
        ->set('location', 'Lviv, Ukraine')
        ->set('linkedin', 'https://linkedin.com/in/ada')
        ->set('professional_function_id', (string) $function->id)
        ->set('role_ids', [(string) $secondary->id, (string) $main->id])
        ->set('skill_ids', $skills->modelKeys())
        ->set('tool_ids', $tools->modelKeys())
        ->set('industry_ids', [(string) $industry->id])
        ->set('hourly_rate', '85.50')
        ->set('offers_monthly_retainers', 'yes')
        ->set('monthly_retainers_description', 'Weekly strategy and reporting')
        ->set('monthly_retainers_from', '2500')
        ->set('weekly_hours', '21-40')
        ->set('open_to_full_time', 'no')
        ->set('best_fit_1', 'Subscription brands')
        ->set('best_fit_3', 'Personal care')
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
        ->and($content->professionalFunctionId())->toBe($function->id)
        ->and($content->roleIds())->toBe([$secondary->id, $main->id])
        ->and($content->skillIds())->toEqualCanonicalizing($skills->modelKeys())
        ->and($content->toolIds())->toEqualCanonicalizing($tools->modelKeys())
        ->and($content->industryIds())->toBe([$industry->id])
        ->and($content->get('hourly_rate_cents'))->toBe(8550)
        ->and($content->get('offers_monthly_retainers'))->toBeTrue()
        ->and($content->get('monthly_retainers_description'))->toBe('Weekly strategy and reporting')
        ->and($content->get('monthly_retainers_from_cents'))->toBe(250000)
        ->and($content->get('weekly_hours'))->toBe('21-40')
        ->and($content->get('open_to_full_time'))->toBeFalse()
        ->and($content->get('best_fit'))->toBe(['Subscription brands', 'Personal care'])
        ->and(array_column($content->experiences(), 'company'))->toBe(['Acme'])
        ->and($profile->openRevision->status)->toBe(ProfileStatus::Draft)
        ->and($profile->isPublished())->toBeFalse()
        ->and($profile->skills()->count())->toBe(0)
        ->and($profile->professional_function_id)->toBeNull();
});

it('shows the technical role fields filled from the roles selection', function () {
    [$user] = freelancerWithProfile();
    [$main, $secondary] = ProfessionalRole::factory()->count(2)->create(['name' => 'Placeholder']);
    $main->update(['name' => 'Growth Marketer']);
    $secondary->update(['name' => 'CRO Expert']);

    Livewire::actingAs($user)
        ->test('pages::freelancer.profile')
        ->assertSee('Hidden Technical Fields')
        ->set('role_ids', [(string) $main->id, (string) $secondary->id])
        ->call('save')
        ->assertSeeInOrder(['main-role', 'Growth Marketer', 'secondary-role', 'CRO Expert', 'third-role']);
});

it('lists the roles under the function they belong to', function () {
    [$user] = freelancerWithProfile();
    $paid = ProfessionalFunction::factory()->create(['name' => 'Paid Media', 'sort_order' => 2]);
    $creative = ProfessionalFunction::factory()->create(['name' => 'Creative', 'sort_order' => 1]);
    ProfessionalRole::factory()->for($paid, 'professionalFunction')->create(['name' => 'Paid Social Marketer']);
    ProfessionalRole::factory()->for($creative, 'professionalFunction')->create(['name' => 'Copywriter']);

    $options = Livewire::actingAs($user)->test('pages::freelancer.profile')->instance()->roleOptions();

    $mine = array_values(array_filter($options, fn (array $option): bool => in_array($option['name'], ['Copywriter', 'Paid Social Marketer'], true)));

    expect(array_column($mine, 'name'))->toBe(['Copywriter', 'Paid Social Marketer'])
        ->and(array_column($mine, 'group'))->toBe(['Creative', 'Paid Media']);
});

it('tells the freelancer that the order of the roles matters', function () {
    [$user] = freelancerWithProfile();

    Livewire::actingAs($user)
        ->test('pages::freelancer.profile')
        ->assertSee('The order of selection matters');
});

it('rejects more roles, skills, tools and industries than allowed', function () {
    [$user] = freelancerWithProfile();

    Livewire::actingAs($user)
        ->test('pages::freelancer.profile')
        ->set('role_ids', ProfessionalRole::factory()->count(4)->create()->modelKeys())
        ->set('skill_ids', Skill::factory()->count(16)->create()->modelKeys())
        ->set('tool_ids', Tool::factory()->count(21)->create()->modelKeys())
        ->set('industry_ids', Industry::factory()->count(11)->create()->modelKeys())
        ->call('save')
        ->assertHasErrors(['role_ids', 'skill_ids', 'tool_ids', 'industry_ids']);
});

it('validates the rates and availability answers', function () {
    [$user] = freelancerWithProfile();

    Livewire::actingAs($user)
        ->test('pages::freelancer.profile')
        ->set('hourly_rate', '0')
        ->set('offers_monthly_retainers', 'maybe')
        ->set('monthly_retainers_from', 'abc')
        ->set('weekly_hours', '100+')
        ->set('open_to_full_time', 'sometimes')
        ->set('best_fit_1', str_repeat('a', 256))
        ->call('save')
        ->assertHasErrors(['hourly_rate', 'offers_monthly_retainers', 'monthly_retainers_from', 'weekly_hours', 'open_to_full_time', 'best_fit_1']);
});

it('only shows the retainer fields when monthly retainers are offered', function () {
    [$user] = freelancerWithProfile();

    Livewire::actingAs($user)
        ->test('pages::freelancer.profile')
        ->assertDontSee('Describe the scope of services your monthly retainer offers')
        ->set('offers_monthly_retainers', 'yes')
        ->assertSee('Describe the scope of services your monthly retainer offers')
        ->assertSee('What do your retainers start at?')
        ->set('offers_monthly_retainers', 'no')
        ->assertDontSee('What do your retainers start at?');
});

it('drops the retainer details when monthly retainers are switched off', function () {
    [$user, $profile] = freelancerWithProfile();

    Livewire::actingAs($user)
        ->test('pages::freelancer.profile')
        ->set('offers_monthly_retainers', 'yes')
        ->set('monthly_retainers_description', 'Weekly reporting')
        ->set('monthly_retainers_from', '2000')
        ->call('save')
        ->set('offers_monthly_retainers', 'no')
        ->call('save');

    $content = $profile->refresh()->openRevision->content();

    expect($content->get('offers_monthly_retainers'))->toBeFalse()
        ->and($content->get('monthly_retainers_description'))->toBeNull()
        ->and($content->get('monthly_retainers_from_cents'))->toBeNull();
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
        ->set('professional_function_id', '')
        ->set('role_ids', [])
        ->set('skill_ids', Skill::factory()->count(2)->create()->modelKeys())
        ->set('tool_ids', Tool::factory()->count(1)->create()->modelKeys())
        ->set('hourly_rate', '')
        ->set('offers_monthly_retainers', '')
        ->set('weekly_hours', '')
        ->set('open_to_full_time', '')
        ->call('submitForReview')
        ->assertHasErrors([
            'professional_title', 'about', 'location', 'professional_function_id', 'role_ids', 'skill_ids', 'tool_ids',
            'hourly_rate', 'offers_monthly_retainers', 'weekly_hours', 'open_to_full_time',
        ]);

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
    completeLiveProfile($profile);

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
