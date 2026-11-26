<?php

use App\Actions\Profiles\GenerateProfileSlug;
use App\Models\FreelancerProfile;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

function profileNamed(string $name): FreelancerProfile
{
    return FreelancerProfile::factory()
        ->for(User::factory()->freelancer()->state(['name' => $name]), 'user')
        ->create();
}

it('starts with the first name alone', function () {
    expect(profileNamed('John Barnstone')->slug)->toBe('john');
});

it('adds the last name when the first name is taken', function () {
    profileNamed('John Smith');

    expect(profileNamed('John Barnstone')->slug)->toBe('john-barnstone');
});

it('adds a six-letter code when the first and last name are taken too', function () {
    profileNamed('John Barnstone');
    profileNamed('John Barnstone');

    $third = profileNamed('John Barnstone');

    expect($third->slug)->toMatch('/^john-barnstone-[a-z0-9]{6}$/');
});

it('gives every profile its own slug however many share a name', function () {
    foreach (range(1, 6) as $ignored) {
        profileNamed('John Barnstone');
    }

    $slugs = FreelancerProfile::query()->pluck('slug');

    expect($slugs)->toHaveCount(6)->and($slugs->unique())->toHaveCount(6)
        ->and($slugs->first())->toBe('john')
        ->and($slugs->get(1))->toBe('john-barnstone');
});

it('goes straight to the code for a one-word name that is taken', function () {
    profileNamed('Madonna');

    expect(profileNamed('Madonna')->slug)->toMatch('/^madonna-[a-z0-9]{6}$/');
});

it('takes the first and the last word of a longer name', function () {
    profileNamed('Mary Smith');

    expect(profileNamed('Mary Jane Watson')->slug)->toBe('mary-watson');
});

it('turns accents and other scripts into plain letters', function () {
    expect(profileNamed('Zoë Müller')->slug)->toBe(Str::slug('Zoë'))
        ->and(profileNamed('Андрій Шевченко')->slug)->toBe(Str::slug('Андрій'));
});

it('falls back to a generic slug when the name has no letters to use', function () {
    expect(profileNamed('🙂')->slug)->toBe('freelancer')
        ->and(profileNamed('🙂')->slug)->toMatch('/^freelancer-[a-z0-9]{6}$/');
});

it('keeps the slug when the freelancer changes their name', function () {
    $profile = profileNamed('John Barnstone');

    $profile->user->update(['name' => 'Jonathan Barnstone']);
    $profile->save();

    expect($profile->refresh()->slug)->toBe('john');
});

it('does not replace a slug that is already set', function () {
    $profile = FreelancerProfile::factory()->create(['slug' => 'custom-address']);

    expect($profile->slug)->toBe('custom-address');
});

it('rejects a duplicate slug at the database level', function () {
    profileNamed('John Barnstone');

    expect(fn () => FreelancerProfile::factory()->create(['slug' => 'john']))->toThrow(QueryException::class);
});

it('generates the same result through the action as through the model', function () {
    profileNamed('John Barnstone');

    expect(app(GenerateProfileSlug::class)('John Barnstone'))->toMatch('/^john-barnstone$/')
        ->and(app(GenerateProfileSlug::class)('John'))->toMatch('/^john-[a-z0-9]{6}$/');
});
