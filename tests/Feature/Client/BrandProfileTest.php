<?php

use App\Models\BrandProfile;
use App\Models\Category;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

it('creates a private brand profile with interests', function () {
    $user = User::factory()->client()->create();
    $categories = Category::factory()->count(2)->create();
    $skill = Skill::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::client.brand-profile')
        ->set('company_name', 'Acme Inc.')
        ->set('description', 'We make everything.')
        ->set('industry', 'Manufacturing')
        ->set('website', 'https://acme.test')
        ->set('linkedin', 'https://linkedin.com/company/acme')
        ->set('contact_email', 'hello@acme.test')
        ->set('category_ids', $categories->pluck('id')->map(fn ($id) => (string) $id)->all())
        ->set('skill_ids', [(string) $skill->id])
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('saved', true);

    $profile = $user->fresh()->brandProfile;

    expect($profile->company_name)->toBe('Acme Inc.')
        ->and($profile->social_links)->toBe(['linkedin' => 'https://linkedin.com/company/acme'])
        ->and($profile->categories->pluck('id')->all())->toEqualCanonicalizing($categories->pluck('id')->all())
        ->and($profile->skills->pluck('id')->all())->toBe([$skill->id]);
});

it('updates the existing brand profile instead of creating a second one', function () {
    $user = User::factory()->client()->create();
    BrandProfile::factory()->create(['user_id' => $user->id, 'company_name' => 'Old Name']);

    Livewire::actingAs($user)
        ->test('pages::client.brand-profile')
        ->assertSet('company_name', 'Old Name')
        ->set('company_name', 'New Name')
        ->call('save')
        ->assertHasNoErrors();

    expect(BrandProfile::count())->toBe(1)
        ->and($user->fresh()->brandProfile->company_name)->toBe('New Name');
});

it('requires a company name and valid urls and emails', function () {
    Livewire::actingAs(User::factory()->client()->create())
        ->test('pages::client.brand-profile')
        ->set('company_name', '')
        ->set('website', 'not-a-url')
        ->set('contact_email', 'not-an-email')
        ->call('save')
        ->assertHasErrors(['company_name', 'website', 'contact_email']);

    expect(BrandProfile::count())->toBe(0);
});

it('rejects a category that does not exist', function () {
    Livewire::actingAs(User::factory()->client()->create())
        ->test('pages::client.brand-profile')
        ->set('company_name', 'Acme')
        ->set('category_ids', ['9999'])
        ->call('save')
        ->assertHasErrors('category_ids.0');
});

it('stores the uploaded logo and removes the replaced one', function () {
    Storage::fake('public');
    $user = User::factory()->client()->create();

    $component = Livewire::actingAs($user)
        ->test('pages::client.brand-profile')
        ->set('company_name', 'Acme');

    $component->set('logo', UploadedFile::fake()->image('one.png'))->call('save')->assertHasNoErrors();
    $first = $user->fresh()->brandProfile->logo_path;
    Storage::disk('public')->assertExists($first);

    $component->set('logo', UploadedFile::fake()->image('two.png'))->call('save')->assertHasNoErrors();

    Storage::disk('public')->assertMissing($first);
    Storage::disk('public')->assertExists($user->fresh()->brandProfile->logo_path);
});

it('only ever shows a client their own brand profile', function () {
    BrandProfile::factory()->create(['company_name' => 'Someone Else Ltd']);
    $user = User::factory()->client()->create();

    Livewire::actingAs($user)
        ->test('pages::client.brand-profile')
        ->assertSet('company_name', '');
});
