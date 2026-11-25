<?php

use App\Actions\Profiles\ApplyProfileRevision;
use App\Actions\Profiles\ProfileContent;
use App\Actions\Profiles\SubmitProfileForReview;
use App\Actions\Profiles\UpdateProfileContent;
use App\Enums\PricingMode;
use App\Enums\ProfileStatus;
use App\Models\ConsultationOffer;
use App\Models\FreelancerProfile;
use App\Models\FreelancerProfileRevision;
use App\Models\Highlight;
use App\Models\Industry;
use App\Models\ProfessionalRole;
use App\Models\Service;
use App\Models\Skill;
use App\Models\Tool;
use Database\Factories\FreelancerProfileRevisionFactory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

function publishedProfile(): FreelancerProfile
{
    return FreelancerProfile::factory()->published()->create();
}

function edit(FreelancerProfile $profile, array $changes): ?FreelancerProfileRevision
{
    return app(UpdateProfileContent::class)($profile, $changes);
}

function apply(?FreelancerProfileRevision $revision): FreelancerProfile
{
    return app(ApplyProfileRevision::class)($revision);
}

it('creates new highlights, services and offers in order when a revision is applied', function () {
    $profile = publishedProfile();

    $revision = edit($profile, [
        'highlights' => [['title' => 'First'], ['title' => 'Second']],
        'services' => [
            ['title' => 'Branding', 'pricing_mode' => 'fixed', 'price_cents' => 150000],
            ['title' => 'Strategy', 'pricing_mode' => 'custom-quote'],
        ],
        'consultation_offers' => [
            ['type' => 'free', 'enabled' => true, 'duration_minutes' => 15, 'availability' => [['day' => 'mon', 'start' => '10:00', 'end' => '12:00']]],
            ['type' => 'paid', 'enabled' => true, 'duration_minutes' => 60, 'price_cents' => 12000, 'description' => 'Deep dive', 'availability' => [['day' => 'tue', 'start' => '09:00', 'end' => '17:00'], ['day' => 'thu', 'start' => '13:00', 'end' => '18:30']]],
        ],
    ]);

    apply($revision);

    expect($profile->highlights->pluck('title')->all())->toBe(['First', 'Second'])
        ->and($profile->services->pluck('title')->all())->toBe(['Branding', 'Strategy'])
        ->and($profile->services[0]->pricing_mode)->toBe(PricingMode::Fixed)
        ->and($profile->services[0]->price_cents)->toBe(150000)
        ->and($profile->services[1]->price_cents)->toBeNull()
        ->and($profile->consultationOffers->pluck('type')->map->value->all())->toBe(['free', 'paid'])
        ->and($profile->consultationOffers->pluck('duration_minutes')->all())->toBe([15, 60])
        ->and($profile->consultationOffers->pluck('price_cents')->all())->toBe([0, 12000])
        ->and($profile->consultationOffers[1]->description)->toBe('Deep dive')
        ->and($profile->consultationOffers[1]->availability)->toBe([
            ['day' => 'tue', 'start' => '09:00', 'end' => '17:00'],
            ['day' => 'thu', 'start' => '13:00', 'end' => '18:30'],
        ]);
});

it('leaves the live offers untouched until the revision is applied', function () {
    $profile = publishedProfile();
    $service = Service::factory()->for($profile, 'profile')->create(['title' => 'Old title']);
    $live = $profile->liveContent();

    $revision = edit($profile, ['services' => [[...$live->services()[0], 'title' => 'New title']]]);

    expect($service->refresh()->title)->toBe('Old title')
        ->and($profile->refresh()->displayStatus())->toBe(ProfileStatus::Draft)
        ->and($revision->content()->services()[0]['title'])->toBe('New title');

    apply($revision);

    expect($service->refresh()->title)->toBe('New title');
});

it('updates live rows in place so their identifiers stay stable', function () {
    $profile = publishedProfile();
    $service = Service::factory()->for($profile, 'profile')->create(['title' => 'Branding', 'sort_order' => 0]);
    $offer = ConsultationOffer::factory()->for($profile, 'profile')->paid()->create(['duration_minutes' => 30, 'price_cents' => 5000]);
    $highlight = Highlight::factory()->for($profile, 'profile')->create(['title' => 'Logo']);
    $live = $profile->liveContent();

    apply(edit($profile, [
        'services' => [[...$live->services()[0], 'title' => 'Brand identity']],
        'consultation_offers' => [$live->consultationOffers()[0], [...$live->consultationOffers()[1], 'price_cents' => 6000]],
        'highlights' => [[...$live->highlights()[0], 'title' => 'Logo suite']],
    ]));

    expect($service->refresh()->title)->toBe('Brand identity')
        ->and($offer->refresh()->price_cents)->toBe(6000)
        ->and($highlight->refresh()->title)->toBe('Logo suite')
        ->and(Service::count())->toBe(1)
        ->and($profile->consultationOffers()->where('type', 'paid')->value('id'))->toBe($offer->id)
        ->and(Highlight::count())->toBe(1);
});

it('keeps identifiers of reordered items and applies the new order', function () {
    $profile = publishedProfile();
    $first = Service::factory()->for($profile, 'profile')->create(['title' => 'First', 'sort_order' => 0]);
    $second = Service::factory()->for($profile, 'profile')->create(['title' => 'Second', 'sort_order' => 1]);
    $live = $profile->liveContent();

    apply(edit($profile, ['services' => array_reverse($live->services())]));

    expect($profile->services->pluck('id')->all())->toBe([$second->id, $first->id])
        ->and($second->refresh()->sort_order)->toBe(0)
        ->and($first->refresh()->sort_order)->toBe(1);
});

it('removes services and highlights that are missing from the revision', function () {
    $profile = publishedProfile();
    $service = Service::factory()->for($profile, 'profile')->create();
    $highlight = Highlight::factory()->for($profile, 'profile')->create();

    apply(edit($profile, ['services' => [], 'highlights' => []]));

    expect(Service::count())->toBe(0)
        ->and(Service::withTrashed()->find($service->id)->trashed())->toBeTrue()
        ->and(Highlight::find($highlight->id))->toBeNull();
});

it('never deletes a consultation: switching it off keeps the row and its settings', function () {
    $profile = publishedProfile();
    $offer = ConsultationOffer::factory()->for($profile, 'profile')->paid()->create(['duration_minutes' => 45, 'price_cents' => 5000]);
    $live = $profile->liveContent();

    apply(edit($profile, ['consultation_offers' => [$live->consultationOffers()[0], [...$live->consultationOffers()[1], 'enabled' => false]]]));

    $offer->refresh();

    expect($offer->is_enabled)->toBeFalse()
        ->and($offer->duration_minutes)->toBe(45)
        ->and($offer->price_cents)->toBe(5000);

    $live = $profile->refresh()->liveContent();
    apply(edit($profile, ['consultation_offers' => [$live->consultationOffers()[0], [...$live->consultationOffers()[1], 'enabled' => true]]]));

    expect($offer->refresh()->is_enabled)->toBeTrue()
        ->and($profile->consultationOffers()->count())->toBe(2);
});

it('keeps exactly one row per consultation type however often revisions are applied', function () {
    $profile = publishedProfile();

    foreach ([45, 60, 90] as $minutes) {
        $live = $profile->refresh()->liveContent();
        apply(edit($profile, ['consultation_offers' => [$live->consultationOffers()[0], [...$live->consultationOffers()[1], 'duration_minutes' => $minutes]]]));
    }

    expect($profile->consultationOffers()->pluck('type')->map->value->all())->toBe(['free', 'paid'])
        ->and($profile->consultationOffers[1]->duration_minutes)->toBe(90);
});

it('never touches another profiles rows even if the revision points at their identifiers', function () {
    $profile = publishedProfile();
    $other = publishedProfile();
    $foreign = Service::factory()->for($other, 'profile')->create(['title' => 'Not yours']);

    apply(edit($profile, ['services' => [['id' => $foreign->id, 'title' => 'Hijacked', 'pricing_mode' => 'custom-quote']]]));

    expect($foreign->refresh()->title)->toBe('Not yours')
        ->and($other->services()->count())->toBe(1)
        ->and($profile->services->pluck('title')->all())->toBe(['Hijacked']);
});

it('keeps highlight media until the revision is applied and then removes what is no longer used', function () {
    Storage::fake('public');
    Storage::disk('public')->put('highlights/cover.jpg', 'c');
    Storage::disk('public')->put('highlights/one.jpg', '1');
    Storage::disk('public')->put('highlights/two.jpg', '2');
    $profile = publishedProfile();
    Highlight::factory()->for($profile, 'profile')->create([
        'cover_path' => 'highlights/cover.jpg',
        'gallery' => ['highlights/one.jpg', 'highlights/two.jpg'],
    ]);
    $live = $profile->liveContent();

    $revision = edit($profile, ['highlights' => [[...$live->highlights()[0], 'cover_path' => null, 'gallery' => ['highlights/two.jpg']]]]);

    Storage::disk('public')->assertExists(['highlights/cover.jpg', 'highlights/one.jpg', 'highlights/two.jpg']);

    apply($revision);

    Storage::disk('public')->assertMissing(['highlights/cover.jpg', 'highlights/one.jpg']);
    Storage::disk('public')->assertExists('highlights/two.jpg');
});

it('goes back to published when offer edits are reverted', function () {
    $profile = publishedProfile();
    Service::factory()->for($profile, 'profile')->create(['title' => 'Branding']);
    $live = $profile->liveContent();

    edit($profile, ['services' => []]);
    expect($profile->refresh()->displayStatus())->toBe(ProfileStatus::Draft);

    expect(edit($profile, ['services' => $live->services()]))->toBeNull()
        ->and($profile->refresh()->displayStatus())->toBe(ProfileStatus::Published);
});

it('refuses to submit more than three services or services that are too long', function () {
    $profile = FreelancerProfile::factory()->create();
    $services = array_map(fn (int $i): array => ['title' => "Service {$i}", 'pricing_mode' => 'custom-quote'], range(1, 4));
    $services[0]['title'] = str_repeat('t', 81);
    $services[1]['description'] = str_repeat('d', 201);
    FreelancerProfileRevision::factory()->draft()->create([
        'freelancer_profile_id' => $profile->id,
        'payload' => ['professional_title' => 'Designer', 'about' => str_repeat('About me. ', 10), 'location' => 'Kyiv', ...FreelancerProfileRevisionFactory::completeDetails(), 'services' => $services],
    ]);

    try {
        app(SubmitProfileForReview::class)($profile);
        $this->fail('Expected a validation exception.');
    } catch (ValidationException $exception) {
        expect($exception->errors()['content'][0])
            ->toContain('You can list at most 3 services.')
            ->toContain('has a title longer than 80 characters')
            ->toContain('is longer than 200 characters');
    }
});

it('refuses to submit a retainer without a description or a starting price', function () {
    $profile = FreelancerProfile::factory()->create();
    FreelancerProfileRevision::factory()->draft()->create([
        'freelancer_profile_id' => $profile->id,
        'payload' => ['professional_title' => 'Designer', 'about' => str_repeat('About me. ', 10), 'location' => 'Kyiv', ...FreelancerProfileRevisionFactory::completeDetails(), 'offers_monthly_retainers' => true],
    ]);

    try {
        app(SubmitProfileForReview::class)($profile);
        $this->fail('Expected a validation exception.');
    } catch (ValidationException $exception) {
        expect(array_keys($exception->errors()))->toEqualCanonicalizing(['monthly_retainers_description', 'monthly_retainers_from']);
    }
});

it('refuses to submit when a selection is below its minimum or above its maximum', function () {
    $profile = FreelancerProfile::factory()->create();
    FreelancerProfileRevision::factory()->draft()->create([
        'freelancer_profile_id' => $profile->id,
        'payload' => [
            'professional_title' => 'Designer', 'about' => str_repeat('About me. ', 10), 'location' => 'Kyiv',
            ...FreelancerProfileRevisionFactory::completeDetails(),
            'skill_ids' => Skill::factory()->count(2)->create()->modelKeys(),
            'tool_ids' => Tool::factory()->count(21)->create()->modelKeys(),
            'role_ids' => ProfessionalRole::factory()->count(4)->create()->modelKeys(),
            'industry_ids' => Industry::factory()->count(11)->create()->modelKeys(),
        ],
    ]);

    try {
        app(SubmitProfileForReview::class)($profile);
        $this->fail('Expected a validation exception.');
    } catch (ValidationException $exception) {
        expect(array_keys($exception->errors()))->toEqualCanonicalizing(['skill_ids', 'tool_ids', 'role_ids', 'industry_ids']);
    }
});

it('normalizes the profile detail fields', function () {
    $content = ProfileContent::fromArray([
        'professional_function_id' => '7',
        'role_ids' => ['3', '1', '3', '2'],
        'offers_monthly_retainers' => false,
        'monthly_retainers_description' => 'Stale text',
        'monthly_retainers_from_cents' => 5000,
        'weekly_hours' => 'unknown',
        'open_to_full_time' => '',
        'best_fit' => ['  First  ', '', 'Second'],
    ]);

    expect($content->professionalFunctionId())->toBe(7)
        ->and($content->roleIds())->toBe([3, 1, 2])
        ->and($content->roleAttributes())->toBe(['main_role_id' => 3, 'secondary_role_id' => 1, 'third_role_id' => 2])
        ->and($content->get('monthly_retainers_description'))->toBeNull()
        ->and($content->get('monthly_retainers_from_cents'))->toBeNull()
        ->and($content->get('weekly_hours'))->toBeNull()
        ->and($content->get('open_to_full_time'))->toBeNull()
        ->and($content->get('best_fit'))->toBe(['First', 'Second']);
});

it('refuses to submit offers that cannot be reviewed', function () {
    $profile = FreelancerProfile::factory()->create();
    FreelancerProfileRevision::factory()->draft()->create([
        'freelancer_profile_id' => $profile->id,
        'payload' => ['professional_title' => 'Designer', 'about' => str_repeat('About me. ', 10), 'location' => 'Kyiv', ...FreelancerProfileRevisionFactory::completeDetails(), 'services' => [['title' => 'Branding', 'pricing_mode' => 'fixed']]],
    ]);

    try {
        app(SubmitProfileForReview::class)($profile);
        $this->fail('Expected a validation exception.');
    } catch (ValidationException $exception) {
        expect($exception->errors()['content'][0])->toContain('The service "Branding" needs a price.');
    }
});
