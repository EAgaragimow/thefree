<?php

use App\Actions\Profiles\OpenProfileRevision;
use App\Enums\ProfileStatus;
use App\Models\ConsultationOffer;
use App\Models\FreelancerProfile;
use App\Models\FreelancerProfileRevision;
use App\Models\Highlight;
use App\Models\Industry;
use App\Models\ProfessionalFunction;
use App\Models\ProfessionalRole;
use App\Models\Service;
use App\Models\Skill;
use App\Models\Tool;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * @return array{0: User, 1: FreelancerProfile}
 */
function pageFreelancer(bool $published = true, ?ProfileStatus $revision = null): array
{
    $user = User::factory()->freelancer()->create();
    $profile = FreelancerProfile::factory()->create(['user_id' => $user->id]);

    if ($published) {
        $profile->forceFill(['published_at' => now(), 'content_updated_at' => now()])->save();
    }

    if (! $published || $revision !== null) {
        FreelancerProfileRevision::factory()->create([
            'freelancer_profile_id' => $profile->id,
            'status' => $revision ?? ProfileStatus::Draft,
        ]);
    }

    return [$user, $profile->refresh()];
}

dataset('offering pages', [
    'dashboard' => ['freelancer.dashboard'],
    'preview' => ['freelancer.preview'],
]);

it('keeps guests and non-freelancers out of the offering pages', function (string $route) {
    $this->get(route($route))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->client()->create())->get(route($route))->assertForbidden();
})->with('offering pages');

it('shows a freelancer each offering page', function (string $route) {
    [$user] = pageFreelancer();

    $this->actingAs($user)->get(route($route))->assertOk();
})->with('offering pages');

it('returns 404 for the preview when a freelancer has no profile', function () {
    $this->actingAs(User::factory()->freelancer()->create())->get(route('freelancer.preview'))->assertNotFound();
});

it('keeps the sections off the dashboard of a freelancer without a profile', function () {
    $this->actingAs(User::factory()->freelancer()->create())->get(route('freelancer.dashboard'))
        ->assertOk()
        ->assertDontSeeHtml('id="highlights"');
});

// Services -----------------------------------------------------------------------------------

it('adds a fixed price service to a draft revision and leaves the live profile alone', function () {
    [$user, $profile] = pageFreelancer();

    Livewire::actingAs($user)
        ->test('freelancer.services-section')
        ->call('startCreating')
        ->set('title', 'Brand identity')
        ->set('description', 'Logo, colours and guidelines.')
        ->set('pricing_mode', 'fixed')
        ->set('price', '1500')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('profile-content-saved');

    $profile->refresh();
    $service = $profile->openRevision->content()->services()[0];

    expect($service)->toMatchArray(['title' => 'Brand identity', 'pricing_mode' => 'fixed', 'price_cents' => 150000, 'id' => null])
        ->and($profile->displayStatus())->toBe(ProfileStatus::Draft)
        ->and($profile->services()->count())->toBe(0);
});

it('stores a price with cents exactly', function () {
    [$user, $profile] = pageFreelancer();

    Livewire::actingAs($user)
        ->test('freelancer.services-section')
        ->set('title', 'Audit')
        ->set('pricing_mode', 'starting-from')
        ->set('price', '99.50')
        ->call('save')
        ->assertHasNoErrors();

    expect($profile->refresh()->openRevision->content()->services()[0]['price_cents'])->toBe(9950);
});

it('does not ask for a price on a custom quote', function () {
    [$user, $profile] = pageFreelancer();

    Livewire::actingAs($user)
        ->test('freelancer.services-section')
        ->set('title', 'Strategy')
        ->set('pricing_mode', 'custom-quote')
        ->set('price', '500')
        ->call('save')
        ->assertHasNoErrors();

    expect($profile->refresh()->openRevision->content()->services()[0])->toMatchArray(['pricing_mode' => 'custom-quote', 'price_cents' => null]);
});

it('validates a service', function (array $input, string|array $errors) {
    [$user] = pageFreelancer();

    $component = Livewire::actingAs($user)->test('freelancer.services-section');

    foreach ($input as $key => $value) {
        $component->set($key, $value);
    }

    $component->call('save')->assertHasErrors($errors);
})->with([
    'missing title' => [['title' => '', 'pricing_mode' => 'custom-quote'], 'title'],
    'fixed without a price' => [['title' => 'A', 'pricing_mode' => 'fixed', 'price' => ''], 'price'],
    'zero price' => [['title' => 'A', 'pricing_mode' => 'fixed', 'price' => '0'], 'price'],
    'too many decimals' => [['title' => 'A', 'pricing_mode' => 'fixed', 'price' => '10.999'], 'price'],
    'text price' => [['title' => 'A', 'pricing_mode' => 'fixed', 'price' => 'free'], 'price'],
    'unknown mode' => [['title' => 'A', 'pricing_mode' => 'barter'], 'pricing_mode'],
    'title over 80 characters' => [['title' => str_repeat('t', 81), 'pricing_mode' => 'custom-quote'], 'title'],
    'description over 200 characters' => [['title' => 'A', 'pricing_mode' => 'custom-quote', 'description' => str_repeat('d', 201)], 'description'],
]);

it('accepts a service title of exactly 80 characters and a description of exactly 200', function () {
    [$user, $profile] = pageFreelancer();

    Livewire::actingAs($user)
        ->test('freelancer.services-section')
        ->set('title', str_repeat('t', 80))
        ->set('description', str_repeat('d', 200))
        ->set('pricing_mode', 'custom-quote')
        ->call('save')
        ->assertHasNoErrors();

    expect($profile->refresh()->openRevision->content()->services())->toHaveCount(1);
});

it('edits a service keeping its identity', function () {
    [$user, $profile] = pageFreelancer();
    $service = Service::factory()->for($profile, 'profile')->create(['title' => 'Old', 'price_cents' => 10000]);
    $uid = "live-{$service->id}";

    Livewire::actingAs($user)
        ->test('freelancer.services-section')
        ->call('startEditing', $uid)
        ->assertSet('title', 'Old')
        ->assertSet('price', '100.00')
        ->set('title', 'New')
        ->call('save')
        ->assertHasNoErrors();

    $saved = $profile->refresh()->openRevision->content()->services();

    expect($saved)->toHaveCount(1)
        ->and($saved[0])->toMatchArray(['id' => $service->id, 'uid' => $uid, 'title' => 'New'])
        ->and($service->refresh()->title)->toBe('Old');
});

it('removes and reorders services', function () {
    [$user, $profile] = pageFreelancer();
    $first = Service::factory()->for($profile, 'profile')->create(['title' => 'First', 'sort_order' => 0]);
    $second = Service::factory()->for($profile, 'profile')->create(['title' => 'Second', 'sort_order' => 1]);
    $third = Service::factory()->for($profile, 'profile')->create(['title' => 'Third', 'sort_order' => 2]);

    $component = Livewire::actingAs($user)
        ->test('freelancer.services-section')
        ->call('sort', "live-{$third->id}", 0);

    expect(array_column($profile->refresh()->openRevision->content()->services(), 'title'))->toBe(['Third', 'First', 'Second']);

    $component->call('delete', "live-{$first->id}");

    expect(array_column($profile->refresh()->openRevision->content()->services(), 'title'))->toBe(['Third', 'Second']);
});

it('cannot edit or reorder a service that is not in the freelancers own profile', function () {
    [$user] = pageFreelancer();
    [, $other] = pageFreelancer();
    $foreign = Service::factory()->for($other, 'profile')->create();

    Livewire::actingAs($user)
        ->test('freelancer.services-section')
        ->call('startEditing', "live-{$foreign->id}")
        ->assertNotFound();

    Livewire::actingAs($user)
        ->test('freelancer.services-section')
        ->call('sort', "live-{$foreign->id}", 0)
        ->assertNotFound();
});

it('refuses to change services while the profile is under review', function () {
    [$user, $profile] = pageFreelancer(true, ProfileStatus::PendingReview);

    Livewire::actingAs($user)
        ->test('freelancer.services-section')
        ->assertDontSeeHtml('wire:click="startCreating"')
        ->set('title', 'Sneaky')
        ->set('pricing_mode', 'custom-quote')
        ->call('save')
        ->assertHasErrors('title');

    expect($profile->refresh()->openRevision->content()->services())->toBe([]);
});

it('limits how many services can be listed', function () {
    [$user, $profile] = pageFreelancer();
    $items = array_map(fn (int $i): array => ['title' => "Service {$i}", 'pricing_mode' => 'custom-quote'], range(1, 3));
    $revision = $profile->openRevision()->first() ?? app(OpenProfileRevision::class)($profile);
    $revision->forceFill(['payload' => $revision->content()->with(['services' => $items])->toArray()])->save();

    Livewire::actingAs($user)
        ->test('freelancer.services-section')
        ->set('title', 'One too many')
        ->set('pricing_mode', 'custom-quote')
        ->call('save')
        ->assertHasErrors('title');

    expect($revision->refresh()->content()->services())->toHaveCount(3);
});

it('hides the add button once three services are listed', function () {
    [$user, $profile] = pageFreelancer();
    $items = array_map(fn (int $i): array => ['title' => "Service {$i}", 'pricing_mode' => 'custom-quote'], range(1, 3));
    $revision = app(OpenProfileRevision::class)($profile);
    $revision->forceFill(['payload' => $revision->content()->with(['services' => $items])->toArray()])->save();

    Livewire::actingAs($user)->test('freelancer.services-section')->assertDontSeeHtml('wire:click="startCreating"');
});

// Consultations ------------------------------------------------------------------------------

function workingOffer(FreelancerProfile $profile, string $type): array
{
    return collect($profile->refresh()->openRevision->content()->consultationOffers())->firstWhere('type', $type);
}

function configureConsultation($component, string $type, array $days = ['mon', 'tue', 'wed', 'thu', 'fri']): void
{
    $component->call('edit', $type)->set('enabled', true);

    foreach ($days as $day) {
        $component->set("availability.{$day}.on", true);
    }
}

it('always lists one free and one paid consultation that cannot be added or removed', function () {
    [$user] = pageFreelancer();

    Livewire::actingAs($user)
        ->test('freelancer.consultations-section')
        ->assertSee('Free consultation')
        ->assertSee('Paid consultation')
        ->assertSee('Not offered')
        ->assertDontSeeHtml('wire:click="startCreating"')
        ->assertDontSeeHtml('wire:click="delete');
});

it('configures the free consultation', function () {
    [$user, $profile] = pageFreelancer();

    $component = Livewire::actingAs($user)->test('freelancer.consultations-section');
    configureConsultation($component, 'free', ['mon', 'wed']);

    $component->set('duration_minutes', '20')
        ->set('description', 'A quick intro call.')
        ->set('availability.mon.start', '10:00')
        ->set('availability.mon.end', '12:30')
        ->call('save')
        ->assertHasNoErrors();

    expect(workingOffer($profile, 'free'))->toMatchArray([
        'enabled' => true,
        'duration_minutes' => 20,
        'price_cents' => 0,
        'description' => 'A quick intro call.',
        'availability' => [
            ['day' => 'mon', 'start' => '10:00', 'end' => '12:30'],
            ['day' => 'wed', 'start' => '09:00', 'end' => '17:00'],
        ],
    ])->and($profile->consultationOffers()->count())->toBe(0);
});

it('configures the paid consultation with a price', function () {
    [$user, $profile] = pageFreelancer();

    $component = Livewire::actingAs($user)->test('freelancer.consultations-section');
    configureConsultation($component, 'paid', ['sat']);

    $component->set('duration_minutes', '60')
        ->set('price', '120')
        ->set('availability.sat.start', '13:00')
        ->set('availability.sat.end', '24:00')
        ->call('save')
        ->assertHasNoErrors();

    expect(workingOffer($profile, 'paid'))->toMatchArray([
        'enabled' => true,
        'duration_minutes' => 60,
        'price_cents' => 12000,
        'availability' => [['day' => 'sat', 'start' => '13:00', 'end' => '24:00']],
    ])->and(workingOffer($profile, 'free')['enabled'])->toBeFalse();
});

it('shows the schedule in 24-hour time', function () {
    [$user, $profile] = pageFreelancer();
    ConsultationOffer::factory()->for($profile, 'profile')->paid()->create([
        'availability' => [['day' => 'mon', 'start' => '13:00', 'end' => '18:30']],
    ]);

    Livewire::actingAs($user)
        ->test('freelancer.consultations-section')
        ->assertSee('Mon 13:00–18:30')
        ->assertDontSee(' PM')
        ->assertDontSee(' AM');
});

it('validates a consultation', function (string $type, array $input, string $error) {
    [$user] = pageFreelancer();

    $component = Livewire::actingAs($user)->test('freelancer.consultations-section');
    configureConsultation($component, $type, ['mon']);

    foreach ($input as $key => $value) {
        $component->set($key, $value);
    }

    $component->call('save')->assertHasErrors($error);
})->with([
    'too short' => ['free', ['duration_minutes' => '5'], 'duration_minutes'],
    'too long' => ['free', ['duration_minutes' => '600'], 'duration_minutes'],
    'not a number' => ['free', ['duration_minutes' => 'long'], 'duration_minutes'],
    'paid without a price' => ['paid', ['price' => ''], 'price'],
    'paid at zero' => ['paid', ['price' => '0'], 'price'],
    'paid below a dollar' => ['paid', ['price' => '0.50'], 'price'],
    'paid with too many decimals' => ['paid', ['price' => '9.999'], 'price'],
    'no days' => ['free', ['availability.mon.on' => false], 'availability'],
    'window ends before it starts' => ['free', ['availability.mon.start' => '17:00', 'availability.mon.end' => '09:00'], 'availability'],
    'window shorter than the call' => ['free', ['duration_minutes' => '60', 'availability.mon.start' => '09:00', 'availability.mon.end' => '09:30'], 'availability'],
    '12-hour start time' => ['free', ['availability.mon.start' => '9:00 AM'], 'availability.mon.start'],
    'time outside the list' => ['free', ['availability.mon.end' => '25:00'], 'availability.mon.end'],
]);

it('keeps the free consultation free whatever price is posted', function () {
    [$user, $profile] = pageFreelancer();

    $component = Livewire::actingAs($user)->test('freelancer.consultations-section');
    configureConsultation($component, 'free', ['mon']);

    $component->set('price', '50')->call('save')->assertHasNoErrors();

    expect(workingOffer($profile, 'free')['price_cents'])->toBe(0);
});

it('lets a consultation be switched off without checking its schedule', function () {
    [$user, $profile] = pageFreelancer();
    ConsultationOffer::factory()->for($profile, 'profile')->paid()->create(['duration_minutes' => 45, 'price_cents' => 8000]);

    Livewire::actingAs($user)
        ->test('freelancer.consultations-section')
        ->call('edit', 'paid')
        ->assertSet('enabled', true)
        ->assertSet('duration_minutes', '45')
        ->assertSet('price', '80.00')
        ->set('enabled', false)
        ->call('save')
        ->assertHasNoErrors();

    expect(workingOffer($profile, 'paid'))->toMatchArray(['enabled' => false, 'duration_minutes' => 45, 'price_cents' => 8000]);
});

it('loads the saved schedule into the form', function () {
    [$user, $profile] = pageFreelancer();
    ConsultationOffer::factory()->for($profile, 'profile')->free()->create([
        'availability' => [['day' => 'tue', 'start' => '08:30', 'end' => '11:00']],
    ]);

    Livewire::actingAs($user)
        ->test('freelancer.consultations-section')
        ->call('edit', 'free')
        ->assertSet('availability.tue', ['on' => true, 'start' => '08:30', 'end' => '11:00'])
        ->assertSet('availability.mon.on', false);
});

it('does not edit a consultation type that does not exist', function () {
    [$user] = pageFreelancer();

    Livewire::actingAs($user)
        ->test('freelancer.consultations-section')
        ->call('edit', 'vip')
        ->assertNotFound();
});

it('refuses to change consultations while the profile is under review', function () {
    [$user, $profile] = pageFreelancer(true, ProfileStatus::PendingReview);

    $component = Livewire::actingAs($user)->test('freelancer.consultations-section')
        ->assertDontSeeHtml('wire:click="edit(');

    configureConsultation($component, 'free', ['mon']);

    $component->call('save')->assertHasErrors('availability');

    expect(workingOffer($profile, 'free')['enabled'])->toBeFalse();
});

// Highlights ---------------------------------------------------------------------------------

it('adds a highlight with a cover, gallery, videos and links', function () {
    Storage::fake('public');
    [$user, $profile] = pageFreelancer();

    Livewire::actingAs($user)
        ->test('freelancer.highlights-section')
        ->call('startCreating')
        ->set('title', 'Acme rebrand')
        ->set('description', 'A full rebrand.')
        ->set('coverUpload', UploadedFile::fake()->image('cover.jpg'))
        ->set('galleryUploads', [UploadedFile::fake()->image('one.jpg'), UploadedFile::fake()->image('two.png')])
        ->call('addVideo')
        ->set('video_urls.0', 'https://youtu.be/dQw4w9WgXcQ')
        ->call('addLink')
        ->set('links.0.label', 'Case study')
        ->set('links.0.url', 'https://example.com/case')
        ->call('save')
        ->assertHasNoErrors();

    $profile->refresh();
    $highlight = $profile->openRevision->content()->highlights()[0];

    expect($highlight)->toMatchArray([
        'title' => 'Acme rebrand',
        'video_urls' => ['https://youtu.be/dQw4w9WgXcQ'],
        'links' => [['label' => 'Case study', 'url' => 'https://example.com/case']],
    ])
        ->and($highlight['gallery'])->toHaveCount(2)
        ->and($profile->highlights()->count())->toBe(0);

    Storage::disk('public')->assertExists([$highlight['cover_path'], ...$highlight['gallery']]);
});

it('validates a highlight', function (array $input, string $error) {
    Storage::fake('public');
    [$user] = pageFreelancer();

    $component = Livewire::actingAs($user)->test('freelancer.highlights-section')->set('title', 'Valid title');

    foreach ($input as $key => $value) {
        $component->set($key, $value);
    }

    $component->call('save')->assertHasErrors($error);
})->with([
    'missing title' => [['title' => ''], 'title'],
    'video from another site' => [['video_urls' => ['https://evil.example/video']], 'video_urls.0'],
    'javascript link' => [['links' => [['label' => 'x', 'url' => 'javascript:alert(1)']]], 'links.0.url'],
    'link without scheme' => [['links' => [['label' => 'x', 'url' => 'example.com']]], 'links.0.url'],
    'too many videos' => [['video_urls' => array_fill(0, 6, 'https://youtu.be/dQw4w9WgXcQ')], 'video_urls'],
    'too many links' => [['links' => array_fill(0, 6, ['label' => 'x', 'url' => 'https://example.com'])], 'links'],
]);

it('rejects files that are not images', function () {
    Storage::fake('public');
    [$user] = pageFreelancer();

    Livewire::actingAs($user)
        ->test('freelancer.highlights-section')
        ->set('title', 'Valid title')
        ->set('coverUpload', UploadedFile::fake()->create('cover.pdf', 10, 'application/pdf'))
        ->call('save')
        ->assertHasErrors('coverUpload');

    Livewire::actingAs($user)
        ->test('freelancer.highlights-section')
        ->set('title', 'Valid title')
        ->set('galleryUploads', [UploadedFile::fake()->create('gallery.pdf', 10, 'application/pdf')])
        ->call('save')
        ->assertHasErrors('galleryUploads.0');
});

it('limits the gallery size', function () {
    Storage::fake('public');
    [$user] = pageFreelancer();

    Livewire::actingAs($user)
        ->test('freelancer.highlights-section')
        ->set('title', 'Valid title')
        ->set('galleryUploads', array_map(fn (int $i) => UploadedFile::fake()->image("{$i}.jpg"), range(1, 13)))
        ->call('save')
        ->assertHasErrors('galleryUploads');
});

it('edits a highlight, dropping a gallery image', function () {
    Storage::fake('public');
    Storage::disk('public')->put('highlights/one.jpg', '1');
    Storage::disk('public')->put('highlights/two.jpg', '2');
    [$user, $profile] = pageFreelancer();
    $highlight = Highlight::factory()->for($profile, 'profile')->create(['title' => 'Old', 'gallery' => ['highlights/one.jpg', 'highlights/two.jpg']]);

    Livewire::actingAs($user)
        ->test('freelancer.highlights-section')
        ->call('startEditing', "live-{$highlight->id}")
        ->assertSet('title', 'Old')
        ->assertSet('gallery', ['highlights/one.jpg', 'highlights/two.jpg'])
        ->call('removeGalleryImage', 0)
        ->set('title', 'New')
        ->call('save')
        ->assertHasNoErrors();

    $saved = $profile->refresh()->openRevision->content()->highlights()[0];

    expect($saved)->toMatchArray(['id' => $highlight->id, 'title' => 'New', 'gallery' => ['highlights/two.jpg']]);
    Storage::disk('public')->assertExists(['highlights/one.jpg', 'highlights/two.jpg']);
});

it('removes and reorders highlights', function () {
    [$user, $profile] = pageFreelancer();
    $a = Highlight::factory()->for($profile, 'profile')->create(['title' => 'A', 'sort_order' => 0]);
    $b = Highlight::factory()->for($profile, 'profile')->create(['title' => 'B', 'sort_order' => 1]);

    $component = Livewire::actingAs($user)
        ->test('freelancer.highlights-section')
        ->call('sort', "live-{$b->id}", 0);

    expect(array_column($profile->refresh()->openRevision->content()->highlights(), 'title'))->toBe(['B', 'A']);

    $component->call('delete', "live-{$a->id}");

    expect(array_column($profile->refresh()->openRevision->content()->highlights(), 'title'))->toBe(['B']);
});

it('does not keep uploaded files when saving a highlight is refused', function () {
    Storage::fake('public');
    [$user, $profile] = pageFreelancer(true, ProfileStatus::PendingReview);

    Livewire::actingAs($user)
        ->test('freelancer.highlights-section')
        ->set('title', 'Too late')
        ->set('coverUpload', UploadedFile::fake()->image('cover.jpg'))
        ->call('save')
        ->assertHasErrors('title');

    expect(Storage::disk('public')->allFiles())->toBe([])
        ->and($profile->refresh()->openRevision->content()->highlights())->toBe([]);
});

it('cannot edit a highlight that is not in the freelancers own profile', function () {
    [$user] = pageFreelancer();
    [, $other] = pageFreelancer();
    $foreign = Highlight::factory()->for($other, 'profile')->create();

    Livewire::actingAs($user)
        ->test('freelancer.highlights-section')
        ->call('startEditing', "live-{$foreign->id}")
        ->assertNotFound();
});

// Revision bar -------------------------------------------------------------------------------

it('lets the freelancer submit the pending changes from any page', function () {
    [$user, $profile] = pageFreelancer(false);

    Livewire::actingAs($user)
        ->test('profile-revision-bar')
        ->assertSee('Your profile is not visible to clients yet')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertDispatched('profile-revision-changed')
        ->assertSee('Your profile has been submitted for review.');

    expect($profile->refresh()->openRevision->status)->toBe(ProfileStatus::PendingReview);
});

it('tells the freelancer what is missing when the profile cannot be submitted', function () {
    $user = User::factory()->freelancer()->create();
    $profile = FreelancerProfile::factory()->create(['user_id' => $user->id]);
    FreelancerProfileRevision::factory()->create(['freelancer_profile_id' => $profile->id, 'payload' => ['about' => 'short']]);

    Livewire::actingAs($user)
        ->test('profile-revision-bar')
        ->call('submit')
        ->assertHasErrors(['professional_title', 'about', 'location', 'professional_function_id', 'role_ids', 'skill_ids', 'tool_ids'])
        ->assertSee('Your profile cannot be submitted yet');

    expect($profile->refresh()->openRevision->status)->toBe(ProfileStatus::Draft);
});

it('shows offers that cannot be reviewed when submitting', function () {
    [$user, $profile] = pageFreelancer(false);
    $revision = $profile->openRevision;
    $revision->forceFill(['payload' => $revision->content()->with(['services' => [['title' => 'Branding', 'pricing_mode' => 'fixed']]])->toArray()])->save();

    Livewire::actingAs($user)
        ->test('profile-revision-bar')
        ->call('submit')
        ->assertHasErrors('content')
        ->assertSee('The service "Branding" needs a price.');
});

it('lets the freelancer discard pending changes of a published profile', function () {
    [$user, $profile] = pageFreelancer();
    Highlight::factory()->for($profile, 'profile')->create(['title' => 'Live']);
    Livewire::actingAs($user)->test('freelancer.highlights-section')->call('delete', 'live-'.$profile->highlights()->first()->id);

    expect($profile->refresh()->displayStatus())->toBe(ProfileStatus::Draft);

    Livewire::actingAs($user)
        ->test('profile-revision-bar')
        ->assertSee('Your last approved version is still live')
        ->call('discard')
        ->assertDispatched('profile-revision-changed');

    expect($profile->refresh()->openRevision)->toBeNull()
        ->and($profile->highlights()->count())->toBe(1);
});

it('offers neither submitting nor discarding while a review is in progress', function () {
    [$user] = pageFreelancer(true, ProfileStatus::PendingReview);

    Livewire::actingAs($user)
        ->test('profile-revision-bar')
        ->assertDontSee('Submit for review')
        ->assertDontSee('Discard changes')
        ->assertDontSeeHtml('fixed inset-x-0');
});

// Preview ------------------------------------------------------------------------------------

it('previews the pending content the way clients will see it', function () {
    [$user, $profile] = pageFreelancer();
    $revision = app(OpenProfileRevision::class)($profile);
    $revision->forceFill(['payload' => $revision->content()->with([
        'professional_title' => 'Preview title',
        'services' => [
            ['title' => 'Fixed service', 'pricing_mode' => 'fixed', 'price_cents' => 150000],
            ['title' => 'From service', 'pricing_mode' => 'starting-from', 'price_cents' => 50000],
            ['title' => 'Quote service', 'pricing_mode' => 'custom-quote'],
        ],
        'consultation_offers' => [['type' => 'paid', 'enabled' => true, 'duration_minutes' => 45, 'price_cents' => 9900, 'availability' => [['day' => 'fri', 'start' => '09:00', 'end' => '17:00']]]],
        'highlights' => [['title' => 'My highlight', 'video_urls' => ['https://youtu.be/dQw4w9WgXcQ'], 'links' => [['label' => 'Site', 'url' => 'https://example.com']]]],
    ])->toArray()])->save();

    $this->actingAs($user)->get(route('freelancer.preview'))
        ->assertOk()
        ->assertSee('Preview title')
        ->assertSee('Fixed service')
        ->assertSee('$1,500.00')
        ->assertSee('From $500.00')
        ->assertSee('Custom quote')
        ->assertSee('Paid consultation')
        ->assertSee('45 minutes')
        ->assertSee('Fri 09:00–17:00')
        ->assertSee('$99.00')
        ->assertSee('My highlight')
        ->assertSee('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', false)
        ->assertSee('https://example.com', false);
});

it('previews the function, roles, catalog selections, rates and best-fit lines', function () {
    [$user, $profile] = pageFreelancer();
    $function = ProfessionalFunction::factory()->create(['name' => 'Paid Media']);
    $main = ProfessionalRole::factory()->create(['name' => 'Paid Social Marketer']);
    $secondary = ProfessionalRole::factory()->create(['name' => 'Growth Marketer']);
    $skill = Skill::factory()->create(['name' => 'Meta Ads']);
    $tool = Tool::factory()->create(['name' => 'Triple Whale']);
    $industry = Industry::factory()->create(['name' => 'Pet Supplies']);
    $revision = app(OpenProfileRevision::class)($profile);
    $revision->forceFill(['payload' => $revision->content()->with([
        'professional_function_id' => $function->id,
        'role_ids' => [$main->id, $secondary->id],
        'skill_ids' => [$skill->id],
        'tool_ids' => [$tool->id],
        'industry_ids' => [$industry->id],
        'hourly_rate_cents' => 12550,
        'offers_monthly_retainers' => true,
        'monthly_retainers_description' => 'Weekly creative testing',
        'monthly_retainers_from_cents' => 300000,
        'weekly_hours' => '11-20',
        'open_to_full_time' => true,
        'best_fit' => ['Brands spending over $500K a month'],
    ])->toArray()])->save();

    $this->actingAs($user)->get(route('freelancer.preview'))
        ->assertOk()
        ->assertSeeInOrder(['Paid Media', 'Paid Social Marketer', 'Growth Marketer'])
        ->assertSee('Meta Ads')
        ->assertSee('Triple Whale')
        ->assertSee('Pet Supplies')
        ->assertSee('$125.50 / hour')
        ->assertSee('Yes, from $3,000.00 / month')
        ->assertSee('Weekly creative testing')
        ->assertSee('11-20 hours per week')
        ->assertSee('Open to full-time opportunities')
        ->assertSee('Brands spending over $500K a month');
});

it('never renders unsafe links or unsupported videos in a profile', function () {
    [$user, $profile] = pageFreelancer();
    $revision = app(OpenProfileRevision::class)($profile);
    $revision->forceFill(['payload' => $revision->content()->with([
        'website' => 'javascript:alert(1)',
        'highlights' => [['title' => 'Tampered', 'video_urls' => ['https://evil.example/embed'], 'links' => [['label' => 'Bad', 'url' => 'javascript:alert(2)']]]],
    ])->toArray()])->save();

    $this->actingAs($user)->get(route('freelancer.preview'))
        ->assertOk()
        ->assertSee('Tampered')
        ->assertDontSee('javascript:alert', false)
        ->assertDontSee('evil.example', false);
});
