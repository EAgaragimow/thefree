<?php

use App\Models\BrandProfile;
use App\Models\Industry;
use App\Models\ProfessionalFunction;
use App\Models\ProfessionalRole;
use App\Models\User;
use Livewire\Livewire;

it('shows a free client the upgrade call to action', function () {
    Livewire::actingAs(User::factory()->client()->create())
        ->test('pages::client.dashboard')
        ->assertSee('Upgrade to the Paid Plan');
});

it('does not show a paid client the upgrade call to action', function () {
    Livewire::actingAs(User::factory()->client()->paid()->create())
        ->test('pages::client.dashboard')
        ->assertDontSee('Upgrade to the Paid Plan');
});

it('asks a client without a complete brand profile to fill it in', function () {
    Livewire::actingAs(User::factory()->client()->create())
        ->test('pages::client.dashboard')
        ->assertSee('Complete your brand profile');
});

it('stops prompting once the brand profile is complete', function () {
    $user = User::factory()->client()->create();
    BrandProfile::factory()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test('pages::client.dashboard')
        ->assertDontSee('Complete your brand profile');
});

it('shows the brand profile on the dashboard', function () {
    $user = User::factory()->client()->create();
    $function = ProfessionalFunction::factory()->create(['name' => 'Paid Media']);
    $role = ProfessionalRole::factory()->for($function, 'professionalFunction')->create(['name' => 'Growth Marketer']);
    $brand = BrandProfile::factory()->create([
        'user_id' => $user->id,
        'company_name' => 'Acme Goods',
        'description' => 'We make everyday things.',
        'location' => 'Austin, United States',
        'contact_email' => 'hello@acme.test',
        'industry_id' => Industry::factory()->create(['name' => 'Home & Garden'])->id,
        'social_links' => ['linkedin' => 'https://linkedin.com/company/acme', 'twitter' => 'javascript:alert(1)'],
    ]);
    $brand->professionalFunctions()->attach($function);
    $brand->professionalRoles()->attach($role);

    Livewire::actingAs($user)->test('pages::client.dashboard')
        ->assertSeeInOrder(['Acme Goods', 'Austin, United States', 'We make everyday things.', 'hello@acme.test', 'Home &amp; Garden', 'Paid Media', 'Growth Marketer', 'LinkedIn', 'Calls'], false)
        ->assertSeeHtml('href="https://linkedin.com/company/acme"')
        ->assertDontSeeHtml('javascript:alert(1)')
        ->assertSeeHtml('href="'.route('client.brand-profile').'"');
});

it('leaves out the brand details that are empty', function () {
    $user = User::factory()->client()->create();
    BrandProfile::factory()->create([
        'user_id' => $user->id,
        'company_name' => 'Bare Co',
        'description' => null,
        'location' => null,
        'contact_email' => null,
        'industry_id' => null,
        'social_links' => [],
    ]);

    Livewire::actingAs($user)->test('pages::client.dashboard')
        ->assertSee('Bare Co')
        ->assertDontSee('Email')
        ->assertDontSee('Industry')
        ->assertDontSee('Functions')
        ->assertDontSee('Roles')
        ->assertDontSee('Social links');
});

it('shows no brand card before the brand profile exists', function () {
    Livewire::actingAs(User::factory()->client()->create())->test('pages::client.dashboard')
        ->assertDontSee('Edit brand profile');
});

it('lists calls and projects as separate groups, calls first', function () {
    Livewire::actingAs(User::factory()->client()->create())->test('pages::client.dashboard')
        ->assertSeeInOrder(['Calls', 'No calls to show.', 'Projects', 'No projects yet.'])
        ->assertDontSee('Recommended freelancers')
        ->assertDontSee('Projects and calls');
});
