<?php

use App\Enums\ProfileStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\FreelancerApplication;
use App\Models\Skill;
use App\Models\User;
use Livewire\Livewire;

function approvedApplication(?string &$token = null, array $overrides = []): FreelancerApplication
{
    return FreelancerApplication::factory()->approved($token)->create(array_merge([
        'email' => 'ada@example.com',
        'full_name' => 'Ada Freelancer',
        'specialty' => 'Brand designer',
        'location' => 'Kyiv, Ukraine',
        'about' => 'Brand designer with a decade of experience.',
        'skills' => ['Figma', 'Branding'],
        'portfolio_links' => ['https://ada.test'],
        'social_links' => ['linkedin' => 'https://linkedin.com/in/ada'],
    ], $overrides));
}

it('shows the activation form for a valid invitation', function () {
    approvedApplication($token);

    $this->get(route('freelancer.activate', $token))->assertOk()->assertSee('Activate your account');
});

it('shows an error for an unknown token', function () {
    $this->get(route('freelancer.activate', 'unknown-token'))
        ->assertOk()
        ->assertSee('This invitation link is invalid or has expired.');
});

it('shows an error for an expired invitation', function () {
    FreelancerApplication::factory()->approved($token)->expiredInvitation()->create();

    $this->get(route('freelancer.activate', $token))->assertSee('This invitation link is invalid or has expired.');
});

it('creates a verified freelancer with a profile prepopulated from the application', function () {
    $application = approvedApplication($token);

    Livewire::test('pages::freelancer.activate', ['token' => $token])
        ->set('password', 'a-Strong-passw0rd!')
        ->set('password_confirmation', 'a-Strong-passw0rd!')
        ->call('activate')
        ->assertHasNoErrors()
        ->assertRedirect(route('freelancer.profile'));

    $user = User::where('email', 'ada@example.com')->firstOrFail();
    $profile = $user->freelancerProfile;
    $draft = $profile->openRevision;

    expect($user->role)->toBe(UserRole::Freelancer)
        ->and($user->name)->toBe('Ada Freelancer')
        ->and($user->hasVerifiedEmail())->toBeTrue()
        ->and($profile->isPublished())->toBeFalse()
        ->and($profile->professional_title)->toBeNull()
        ->and($draft->status)->toBe(ProfileStatus::Draft)
        ->and($draft->content()->get('professional_title'))->toBe('Brand designer')
        ->and($draft->content()->get('about'))->toBe('Brand designer with a decade of experience.')
        ->and($draft->content()->get('location'))->toBe('Kyiv, Ukraine')
        ->and($draft->content()->get('website'))->toBe('https://ada.test')
        ->and($draft->content()->get('social_links'))->toBe(['linkedin' => 'https://linkedin.com/in/ada'])
        ->and(Skill::whereIn('id', $draft->content()->skillIds())->pluck('name')->all())->toEqualCanonicalizing(['Figma', 'Branding'])
        ->and($application->refresh()->user_id)->toBe($user->id)
        ->and($application->activated_at)->not->toBeNull()
        ->and($application->invitation_token_hash)->toBeNull();

    $this->assertAuthenticatedAs($user);
    expect(AuditLog::where('action', 'application.activated')->exists())->toBeTrue();
});

it('reuses existing skills instead of duplicating them', function () {
    Skill::factory()->create(['name' => 'Figma', 'slug' => 'figma']);
    approvedApplication($token);

    Livewire::test('pages::freelancer.activate', ['token' => $token])
        ->set('password', 'a-Strong-passw0rd!')
        ->set('password_confirmation', 'a-Strong-passw0rd!')
        ->call('activate');

    expect(Skill::where('slug', 'figma')->count())->toBe(1);
});

it('cannot be used twice', function () {
    approvedApplication($token);

    Livewire::test('pages::freelancer.activate', ['token' => $token])
        ->set('password', 'a-Strong-passw0rd!')
        ->set('password_confirmation', 'a-Strong-passw0rd!')
        ->call('activate');

    auth()->logout();

    Livewire::test('pages::freelancer.activate', ['token' => $token])
        ->set('password', 'another-Passw0rd!')
        ->set('password_confirmation', 'another-Passw0rd!')
        ->call('activate')
        ->assertHasErrors('token');

    expect(User::where('email', 'ada@example.com')->count())->toBe(1);
});

it('does not activate an expired invitation', function () {
    FreelancerApplication::factory()->approved($token)->expiredInvitation()->create();

    Livewire::test('pages::freelancer.activate', ['token' => $token])
        ->set('password', 'a-Strong-passw0rd!')
        ->set('password_confirmation', 'a-Strong-passw0rd!')
        ->call('activate')
        ->assertHasErrors('token');

    expect(User::where('role', 'freelancer')->count())->toBe(0);
});

it('does not activate when an account with the same email already exists', function () {
    approvedApplication($token);
    User::factory()->create(['email' => 'ada@example.com']);

    Livewire::test('pages::freelancer.activate', ['token' => $token])
        ->set('password', 'a-Strong-passw0rd!')
        ->set('password_confirmation', 'a-Strong-passw0rd!')
        ->call('activate')
        ->assertHasErrors('token');
});

it('requires a confirmed password', function () {
    approvedApplication($token);

    Livewire::test('pages::freelancer.activate', ['token' => $token])
        ->set('password', 'a-Strong-passw0rd!')
        ->set('password_confirmation', 'different')
        ->call('activate')
        ->assertHasErrors('password');

    expect(User::where('role', 'freelancer')->count())->toBe(0);
});

it('blocks signed-in users from the activation page', function () {
    approvedApplication($token);

    $this->actingAs(User::factory()->create())
        ->get(route('freelancer.activate', $token))
        ->assertRedirect();
});
