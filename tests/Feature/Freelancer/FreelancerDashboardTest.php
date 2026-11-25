<?php

use App\Models\FreelancerProfile;
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
