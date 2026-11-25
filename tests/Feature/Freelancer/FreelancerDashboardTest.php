<?php

use App\Models\ConsultationOffer;
use App\Models\FreelancerExperience;
use App\Models\FreelancerProfile;
use App\Models\FreelancerProfileRevision;
use App\Models\Highlight;
use App\Models\Service;
use Livewire\Livewire;

it('links to the preview and the profile settings', function () {
    $profile = FreelancerProfile::factory()->published()->create();

    Livewire::actingAs($profile->user)->test('pages::freelancer.dashboard')
        ->assertSee('Preview Profile')
        ->assertSeeHtml('href="'.route('freelancer.preview').'"')
        ->assertSee('Profile Settings')
        ->assertSeeHtml('href="'.route('freelancer.profile').'"')
        ->assertDontSee('Open my profile');
});

it('tags a newbie next to the profile status', function () {
    $profile = FreelancerProfile::factory()->published()->newbie()->create();

    Livewire::actingAs($profile->user)->test('pages::freelancer.dashboard')
        ->assertSeeInOrder(['Published', 'Newbie']);
});

it('does not tag an established freelancer', function () {
    $profile = FreelancerProfile::factory()->published()->create();

    Livewire::actingAs($profile->user)->test('pages::freelancer.dashboard')
        ->assertSee('Published')
        ->assertDontSee('Newbie');
});

it('groups highlights, services, work history and consultations with a link to each settings page', function () {
    $profile = FreelancerProfile::factory()->published()->create();
    Highlight::factory()->create(['freelancer_profile_id' => $profile->id, 'title' => 'Skincare relaunch']);
    Service::factory()->create(['freelancer_profile_id' => $profile->id, 'title' => 'Channel audit', 'price_cents' => 75000]);
    FreelancerExperience::factory()->create(['freelancer_profile_id' => $profile->id, 'company' => 'Northwind Studio', 'role' => 'Growth Marketer']);
    ConsultationOffer::factory()->free()->create(['freelancer_profile_id' => $profile->id, 'duration_minutes' => 20]);

    Livewire::actingAs($profile->user)->test('pages::freelancer.dashboard')
        ->assertSeeInOrder(['Highlights', 'Skincare relaunch', 'Services', 'Channel audit', '$750.00', 'Work history', 'Growth Marketer · Northwind Studio', 'Consultations', 'Free consultation', '20 minutes'])
        ->assertDontSee('No items yet.')
        ->assertDontSee('Go to settings')
        ->assertSeeHtml('href="'.route('freelancer.highlights').'"')
        ->assertSeeHtml('href="'.route('freelancer.services').'"')
        ->assertSeeHtml('href="'.route('freelancer.profile').'"')
        ->assertSeeHtml('href="'.route('freelancer.consultations').'"');
});

it('says there are no items yet and offers the settings when a group is empty', function () {
    $profile = FreelancerProfile::factory()->published()->create();

    Livewire::actingAs($profile->user)->test('pages::freelancer.dashboard')
        ->assertSee('No items yet.', 4)
        ->assertSee('Go to settings', 4);
});

it('leaves out a consultation that is switched off', function () {
    $profile = FreelancerProfile::factory()->published()->create();
    ConsultationOffer::factory()->free()->disabled()->create(['freelancer_profile_id' => $profile->id]);

    Livewire::actingAs($profile->user)->test('pages::freelancer.dashboard')
        ->assertDontSee('Free consultation')
        ->assertSee('No items yet.', 4);
});

it('shows pending changes rather than the live items', function () {
    $profile = FreelancerProfile::factory()->published()->create();
    Service::factory()->create(['freelancer_profile_id' => $profile->id, 'title' => 'Live service']);
    $revision = FreelancerProfileRevision::factory()->draft()->create(['freelancer_profile_id' => $profile->id]);
    $revision->update(['payload' => $revision->content()->with(['services' => [[
        'title' => 'Draft service', 'pricing_mode' => 'custom-quote',
    ]]])->toArray()]);

    Livewire::actingAs($profile->user)->test('pages::freelancer.dashboard')
        ->assertSee('Draft service')
        ->assertSee('Custom quote')
        ->assertDontSee('Live service');
});
