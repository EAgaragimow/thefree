<?php

use App\Actions\Directory\ToggleFavorite;
use App\Models\FreelancerProfile;
use App\Models\ProfessionalFunction;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Livewire\Livewire;

it('lets a paid client save and remove a freelancer from the directory', function () {
    $client = User::factory()->client()->paid()->create();
    $profile = directoryProfile();

    $component = Livewire::actingAs($client)->test('pages::directory.index');

    $component->call('toggleFavorite', $profile->id);
    expect($client->favoriteFreelancers()->pluck('freelancer_profiles.id')->all())->toBe([$profile->id]);

    $component->call('toggleFavorite', $profile->id);
    expect($client->favoriteFreelancers()->count())->toBe(0);
});

it('lets a paid client save a freelancer from the profile page', function () {
    $client = User::factory()->client()->paid()->create();
    $profile = directoryProfile();

    Livewire::actingAs($client)->test('pages::directory.show', ['profile' => $profile->id])
        ->assertSee('Save to favorites')
        ->call('toggleFavorite', $profile->id)
        ->assertSee('Saved to favorites');

    expect($client->favoriteFreelancers()->count())->toBe(1);
});

it('keeps favorites to paid clients', function (string $state) {
    $client = match ($state) {
        'free' => User::factory()->client()->create(),
        'payment-restricted' => User::factory()->client()->paymentRestricted()->create(),
        'freelancer' => User::factory()->freelancer()->create(),
    };
    $profile = directoryProfile();

    Livewire::actingAs($client)->test('pages::directory.index')
        ->call('toggleFavorite', $profile->id)
        ->assertForbidden();

    expect($client->favoriteFreelancers()->count())->toBe(0);
})->with(['free', 'payment-restricted', 'freelancer']);

it('does not let a lapsed client remove what they saved', function () {
    $client = User::factory()->client()->paymentRestricted()->create();
    $profile = directoryProfile();
    $client->favoriteFreelancers()->attach($profile);

    expect(fn () => app(ToggleFavorite::class)($client, $profile))->toThrow(AuthorizationException::class)
        ->and($client->favoriteFreelancers()->count())->toBe(1);
});

it('does not save a freelancer who is not visible', function (array $attributes, array $user) {
    $client = User::factory()->client()->paid()->create();
    $profile = directoryProfile($attributes, $user);

    Livewire::actingAs($client)->test('pages::directory.index')
        ->call('toggleFavorite', $profile->id)
        ->assertNotFound();

    expect($client->favoriteFreelancers()->count())->toBe(0);
})->with([
    'never published' => [['published_at' => null], []],
    'suspended' => [[], ['status' => 'suspended']],
]);

it('lists favourites with links for a paid client', function () {
    $client = User::factory()->client()->paid()->create();
    $profile = directoryProfile(user: ['name' => 'Grace Hopper']);
    $client->favoriteFreelancers()->attach($profile);

    Livewire::actingAs($client)->test('pages::client.favorites')
        ->assertSee('Favourites')
        ->assertSee('Grace Hopper')
        ->assertSee(route('directory.show', $profile));
});

it('keeps listing favourites after a lapse, without links', function () {
    $client = User::factory()->client()->paid()->create();
    $profile = directoryProfile(user: ['name' => 'Grace Hopper']);
    $client->favoriteFreelancers()->attach($profile);

    $client->forceFill(['subscription_type' => 'free-plan'])->save();

    Livewire::actingAs($client)->test('pages::client.favorites')
        ->assertSee('Favourites')
        ->assertSee('Grace Hopper')
        ->assertDontSee(route('directory.show', $profile))
        ->assertSee('Upgrade to the Paid Plan');

    expect($client->favoriteFreelancers()->count())->toBe(1);
});

it('leaves hidden freelancers out of the favorites list but keeps the row', function () {
    $client = User::factory()->client()->paid()->create();
    $profile = directoryProfile(user: ['name' => 'Grace Hopper']);
    $client->favoriteFreelancers()->attach($profile);
    $profile->user->forceFill(['status' => 'suspended'])->save();

    Livewire::actingAs($client)->test('pages::client.favorites')
        ->assertDontSee('Grace Hopper');

    expect($client->favoriteFreelancers()->count())->toBe(1);

    $profile->user->forceFill(['status' => 'active'])->save();

    Livewire::actingAs($client)->test('pages::client.favorites')
        ->assertSee('Grace Hopper');
});

it('removes favorites together with the freelancer profile', function () {
    $client = User::factory()->client()->paid()->create();
    $profile = directoryProfile();
    $client->favoriteFreelancers()->attach($profile);

    $profile->delete();

    expect($client->favoriteFreelancers()->count())->toBe(0);
});

it('recommends matching freelancers on the dashboard', function () {
    $function = ProfessionalFunction::factory()->create();
    $client = User::factory()->client()->paid()->create();
    $client->brandProfile()->create(['company_name' => 'Acme'])->professionalFunctions()->attach($function);

    directoryProfile(['professional_function_id' => $function->id], ['name' => 'Right fit']);

    Livewire::actingAs($client)->test('pages::client.dashboard')
        ->assertSee('Recommended freelancers')
        ->assertSee('Right fit');
});

it('asks a free client to upgrade when they click a recommendation', function () {
    $profile = directoryProfile(user: ['name' => 'Right fit']);

    Livewire::actingAs(User::factory()->client()->create())->test('pages::client.dashboard')
        ->assertSee('Right fit')
        ->assertDontSee(route('directory.show', $profile))
        ->assertSeeHtml('data-modal="upgrade"');
});

it('counts a favorite only once per client', function () {
    $client = User::factory()->client()->paid()->create();
    $profile = FreelancerProfile::factory()->published()->create();

    app(ToggleFavorite::class)($client, $profile);

    expect(fn () => $client->favoriteFreelancers()->attach($profile))->toThrow(QueryException::class);
});

it('lists the recommendations from the highest score to the lowest', function () {
    $function = ProfessionalFunction::factory()->create();
    $client = User::factory()->client()->paid()->create();
    $client->brandProfile()->create(['company_name' => 'Acme'])->professionalFunctions()->attach($function);

    foreach ([300 => 'Third', 90000 => 'First', 5000 => 'Second', 0 => 'Fourth'] as $score => $name) {
        directoryProfile(['professional_function_id' => $function->id, 'score' => $score], ['name' => $name]);
    }

    Livewire::actingAs($client)->test('pages::client.dashboard')
        ->assertSeeInOrder(['First', 'Second', 'Third', 'Fourth']);
});

it('keeps favourites off the dashboard and on their own page', function () {
    $client = User::factory()->client()->paid()->create();
    $client->favoriteFreelancers()->attach(directoryProfile(user: ['name' => 'Grace Hopper']));

    Livewire::actingAs($client)->test('pages::client.dashboard')
        ->assertDontSee('Favourites')
        ->assertDontSee('Favorites');

    $this->actingAs($client)->get(route('client.favorites'))
        ->assertOk()
        ->assertSee('Favourites')
        ->assertSee('Grace Hopper');
});

it('says so when nothing is saved yet', function () {
    Livewire::actingAs(User::factory()->client()->paid()->create())->test('pages::client.favorites')
        ->assertSee('You have not saved anyone yet');
});

it('lets a saved freelancer be removed from the favourites page', function () {
    $client = User::factory()->client()->paid()->create();
    $client->favoriteFreelancers()->attach($profile = directoryProfile(user: ['name' => 'Grace Hopper']));

    Livewire::actingAs($client)->test('pages::client.favorites')
        ->assertSee('Grace Hopper')
        ->call('toggleFavorite', $profile->id)
        ->assertDontSee('Grace Hopper');
});

it('gives the favourites page to clients only', function () {
    $this->actingAs(User::factory()->freelancer()->create())->get(route('client.favorites'))->assertForbidden();
});

it('shows the Favourites tab to brands only', function () {
    $this->actingAs(User::factory()->client()->create())->get(route('client.dashboard'))
        ->assertSee('href="'.route('client.favorites').'"', false);

    $this->actingAs(User::factory()->freelancer()->create())->get(route('freelancer.dashboard'))
        ->assertDontSee('href="'.route('client.favorites').'"', false);
});
