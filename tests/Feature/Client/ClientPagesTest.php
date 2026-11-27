<?php

use App\Models\ProfessionalFunction;
use App\Models\User;
use Livewire\Livewire;

it('sends guests to the login page and keeps freelancers out', function (string $route) {
    $this->get(route($route))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->freelancer()->create())->get(route($route))->assertForbidden();
})->with(['client.recommended']);

it('opens the pages for clients on either plan', function (string $route, string $text) {
    $this->actingAs(User::factory()->client()->create())->get(route($route))->assertOk()->assertSee($text);
    $this->actingAs(User::factory()->client()->paid()->create())->get(route($route))->assertOk()->assertSee($text);
})->with([
    'recommended' => ['client.recommended', 'Recommended freelancers'],
]);

it('has no separate pages for calls and projects', function () {
    $client = User::factory()->client()->create();

    $this->actingAs($client)->get('/client/calls')->assertNotFound();
    $this->actingAs($client)->get('/client/projects')->assertNotFound();
});

it('keeps the recommendations off the dashboard and lists all of them on their own page, best score first', function () {
    $function = ProfessionalFunction::factory()->create();
    $client = User::factory()->client()->paid()->create();
    $client->brandProfile()->create(['company_name' => 'Acme'])->professionalFunctions()->attach($function);

    foreach (range(1, 8) as $score) {
        directoryProfile(['professional_function_id' => $function->id, 'score' => $score * 100], ['name' => "Freelancer {$score}"]);
    }

    Livewire::actingAs($client)->test('pages::client.dashboard')
        ->assertDontSee('Recommended freelancers')
        ->assertDontSee('Freelancer 8');

    Livewire::actingAs($client)->test('pages::client.recommended')
        ->assertSeeInOrder(['Freelancer 8', 'Freelancer 7', 'Freelancer 6', 'Freelancer 5', 'Freelancer 4', 'Freelancer 3', 'Freelancer 2', 'Freelancer 1']);
});

it('keeps the recommendations of a free client to cards that ask to upgrade', function () {
    $profile = directoryProfile(user: ['name' => 'Right fit']);

    Livewire::actingAs(User::factory()->client()->create())->test('pages::client.recommended')
        ->assertSee('Right fit')
        ->assertDontSee(route('directory.show', $profile->slug))
        ->assertSeeHtml('data-modal="upgrade"');
});

it('lets a paid client save a recommended freelancer', function () {
    $client = User::factory()->client()->paid()->create();
    $profile = directoryProfile();

    Livewire::actingAs($client)->test('pages::client.recommended')
        ->call('toggleFavorite', $profile->id);

    expect($client->favoriteFreelancers()->count())->toBe(1);
});

it('says so when there is nobody to recommend', function () {
    Livewire::actingAs(User::factory()->client()->create())->test('pages::client.recommended')
        ->assertSee('No freelancers to recommend yet');
});

it('shows the account menu links to brands only', function () {
    $links = ['client.recommended', 'client.favorites'];

    $client = $this->actingAs(User::factory()->client()->create())->get(route('client.dashboard'));
    $freelancer = $this->actingAs(User::factory()->freelancer()->create())->get(route('freelancer.dashboard'));

    foreach ($links as $route) {
        $client->assertSee('href="'.route($route).'"', false);
        $freelancer->assertDontSee('href="'.route($route).'"', false);
    }
});
