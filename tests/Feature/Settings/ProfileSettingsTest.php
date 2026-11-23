<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

it('renders the profile settings for a signed-in user', function () {
    $this->actingAs(User::factory()->create())->get(route('settings.profile'))->assertOk()->assertSee('Profile');
});

it('updates name, email and time zone', function () {
    $user = User::factory()->create(['email' => 'old@example.com']);

    Livewire::actingAs($user)
        ->test('pages::settings.profile')
        ->set('name', 'New Name')
        ->set('email', 'old@example.com')
        ->set('timezone', 'America/New_York')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('saved', true);

    expect($user->fresh())
        ->name->toBe('New Name')
        ->timezone->toBe('America/New_York');
});

it('requires the new email address to be verified again', function () {
    $user = User::factory()->create(['email' => 'old@example.com']);

    Livewire::actingAs($user)
        ->test('pages::settings.profile')
        ->set('email', 'new@example.com')
        ->call('save')
        ->assertHasNoErrors();

    expect($user->fresh())
        ->email->toBe('new@example.com')
        ->email_verified_at->toBeNull();
});

it('rejects an email address used by another account', function () {
    User::factory()->create(['email' => 'taken@example.com']);
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::settings.profile')
        ->set('email', 'taken@example.com')
        ->call('save')
        ->assertHasErrors('email');
});

it('rejects an invalid time zone', function () {
    Livewire::actingAs(User::factory()->create())
        ->test('pages::settings.profile')
        ->set('timezone', 'Mars/Base')
        ->call('save')
        ->assertHasErrors('timezone');
});

it('stores an uploaded avatar and replaces the previous one', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)->test('pages::settings.profile');

    $component->set('avatar', UploadedFile::fake()->image('first.png'))->call('save')->assertHasNoErrors();
    $firstPath = $user->fresh()->avatar_path;
    Storage::disk('public')->assertExists($firstPath);

    $component->set('avatar', UploadedFile::fake()->image('second.png'))->call('save')->assertHasNoErrors();

    Storage::disk('public')->assertMissing($firstPath);
    Storage::disk('public')->assertExists($user->fresh()->avatar_path);
});

it('rejects an avatar that is not an image', function () {
    Storage::fake('public');

    Livewire::actingAs(User::factory()->create())
        ->test('pages::settings.profile')
        ->set('avatar', UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf'))
        ->call('save')
        ->assertHasErrors('avatar');
});
