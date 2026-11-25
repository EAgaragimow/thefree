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

it('shows favorites on the dashboard with links for a paid client', function () {
    $client = User::factory()->client()->paid()->create();
    $profile = directoryProfile(user: ['name' => 'Grace Hopper']);
    $client->favoriteFreelancers()->attach($profile);

    Livewire::actingAs($client)->test('pages::client.dashboard')
        ->assertSee('Favorites')
        ->assertSee('Grace Hopper')
        ->assertSee(route('directory.show', $profile));
});

it('keeps showing favorites after a lapse, without links', function () {
    $client = User::factory()->client()->paid()->create();
    $profile = directoryProfile(user: ['name' => 'Grace Hopper']);
    $client->favoriteFreelancers()->attach($profile);

    $client->forceFill(['subscription_type' => 'free-plan'])->save();

    Livewire::actingAs($client)->test('pages::client.dashboard')
        ->assertSee('Favorites')
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

    Livewire::actingAs($client)->test('pages::client.dashboard')
        ->assertDontSee('Grace Hopper');

    expect($client->favoriteFreelancers()->count())->toBe(1);

    $profile->user->forceFill(['status' => 'active'])->save();

    Livewire::actingAs($client)->test('pages::client.dashboard')
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
