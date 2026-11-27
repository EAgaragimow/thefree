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

it('lists highlights, services, work history and consultations as separate sections', function () {
    $profile = FreelancerProfile::factory()->published()->create();
    Highlight::factory()->create(['freelancer_profile_id' => $profile->id, 'title' => 'Skincare relaunch']);
    Service::factory()->create(['freelancer_profile_id' => $profile->id, 'title' => 'Channel audit', 'price_cents' => 75000]);
    FreelancerExperience::factory()->create(['freelancer_profile_id' => $profile->id, 'company' => 'Northwind Studio', 'role' => 'Growth Marketer']);
    ConsultationOffer::factory()->free()->create(['freelancer_profile_id' => $profile->id, 'duration_minutes' => 20]);

    Livewire::actingAs($profile->user)->test('pages::freelancer.dashboard')
        ->assertSeeInOrder(['Highlights', 'Skincare relaunch', 'Services', 'Channel audit', '$750.00', 'Work history', 'Growth Marketer · Northwind Studio', 'Consultations', 'Free consultation', '20 minutes'])
        ->assertSeeHtml('id="highlights"')
        ->assertSeeHtml('id="services"')
        ->assertSeeHtml('id="work-history"')
        ->assertSeeHtml('id="consultations"')
        ->assertDontSee('No highlights yet.')
        ->assertDontSee('Go to settings');
});

it('says so when a section has nothing in it yet', function () {
    $profile = FreelancerProfile::factory()->published()->create();

    Livewire::actingAs($profile->user)->test('pages::freelancer.dashboard')
        ->assertSee('No highlights yet.')
        ->assertSee('No services yet.')
        ->assertSee('No work history yet.')
        ->assertDontSee('Go to settings');
});

it('shows a consultation that is switched off as not offered', function () {
    $profile = FreelancerProfile::factory()->published()->create();
    ConsultationOffer::factory()->free()->disabled()->create(['freelancer_profile_id' => $profile->id]);

    Livewire::actingAs($profile->user)->test('pages::freelancer.dashboard')
        ->assertSee('Free consultation')
        ->assertSee('Not offered');
});

it('has an anchor for every section of the tab bar', function () {
    $profile = FreelancerProfile::factory()->published()->create();

    $response = $this->actingAs($profile->user)->get(route('freelancer.dashboard'))->assertOk();

    foreach (['overview', 'messages', 'requests', 'calls', 'projects', 'highlights', 'services', 'work-history', 'consultations'] as $anchor) {
        $response->assertSeeHtml('href="#'.$anchor.'"')->assertSeeHtml('id="'.$anchor.'"');
    }

    $response->assertSeeInOrder(['Overview', 'Messages', 'Requests', 'Calls', 'Projects', 'Highlights', 'Services', 'Work history', 'Consultations'])
        ->assertSeeHtml('sticky top-0');
});

it('has no separate pages for highlights, services and consultations', function () {
    $profile = FreelancerProfile::factory()->published()->create();

    foreach (['highlights', 'services', 'consultations'] as $page) {
        $this->actingAs($profile->user)->get('/freelancer/'.$page)->assertNotFound();
    }
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

it('lists requests, calls and projects above the profile groups', function () {
    $profile = FreelancerProfile::factory()->published()->create();

    Livewire::actingAs($profile->user)->test('pages::freelancer.dashboard')
        ->assertSeeInOrder(['Messages', 'Requests', 'Calls', 'Projects', 'Highlights', 'Services', 'Work history', 'Consultations'])
        ->assertSee('No messages to show.')
        ->assertSee('No call requests to show.')
        ->assertSee('No calls to show.')
        ->assertSee('No projects yet.')
        ->assertDontSee('Requests and projects');
});
