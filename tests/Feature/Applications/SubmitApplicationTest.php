<?php

use App\Enums\ApplicationStatus;
use App\Models\FreelancerApplication;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

function validApplication(array $overrides = []): array
{
    return array_merge([
        'full_name' => 'Ada Freelancer',
        'email' => 'ada@example.com',
        'location' => 'Kyiv, Ukraine',
        'specialty' => 'Brand designer',
        'years_experience' => 7,
        'skills' => 'Figma, Branding, Figma',
        'experience_summary' => str_repeat('I have designed brands for many companies. ', 3),
        'portfolio_links' => "https://ada.test\nhttps://behance.test/ada",
        'linkedin' => 'https://linkedin.com/in/ada',
    ], $overrides);
}

function fillApplication($component, array $data)
{
    foreach ($data as $key => $value) {
        $component->set($key, $value);
    }

    return $component;
}

it('is visible to guests', function () {
    $this->get(route('freelancer.apply'))->assertOk()->assertSee('Become a Freelancer');
});

it('stores a pending application with normalised skills and links', function () {
    fillApplication(Livewire::test('pages::freelancer.apply'), validApplication())
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('submitted', true);

    $application = FreelancerApplication::firstOrFail();

    expect($application->status)->toBe(ApplicationStatus::Pending)
        ->and($application->email)->toBe('ada@example.com')
        ->and($application->skills)->toBe(['Figma', 'Branding'])
        ->and($application->portfolio_links)->toBe(['https://ada.test', 'https://behance.test/ada'])
        ->and($application->social_links)->toBe(['linkedin' => 'https://linkedin.com/in/ada']);
});

it('requires the core fields', function () {
    Livewire::test('pages::freelancer.apply')
        ->call('submit')
        ->assertHasErrors(['full_name', 'email', 'location', 'specialty', 'skills', 'experience_summary', 'portfolio_links']);

    expect(FreelancerApplication::count())->toBe(0);
});

it('rejects a portfolio link that is not a url', function () {
    fillApplication(Livewire::test('pages::freelancer.apply'), validApplication(['portfolio_links' => "https://ada.test\nnot a link"]))
        ->call('submit')
        ->assertHasErrors('portfolio_links');

    expect(FreelancerApplication::count())->toBe(0);
});

it('rejects an email that already has a pending application', function () {
    FreelancerApplication::factory()->create(['email' => 'ada@example.com']);

    fillApplication(Livewire::test('pages::freelancer.apply'), validApplication())
        ->call('submit')
        ->assertHasErrors('email');

    expect(FreelancerApplication::count())->toBe(1);
});

it('rejects an email that already belongs to an account', function () {
    User::factory()->create(['email' => 'ada@example.com']);

    fillApplication(Livewire::test('pages::freelancer.apply'), validApplication())
        ->call('submit')
        ->assertHasErrors('email');
});

it('lets an applicant whose application was rejected apply again', function () {
    FreelancerApplication::factory()->rejected()->create(['email' => 'ada@example.com']);

    fillApplication(Livewire::test('pages::freelancer.apply'), validApplication())
        ->call('submit')
        ->assertHasNoErrors();

    expect(FreelancerApplication::count())->toBe(2);
});

it('silently discards submissions that fill in the honeypot field', function () {
    fillApplication(Livewire::test('pages::freelancer.apply'), validApplication(['company_website' => 'https://spam.test']))
        ->call('submit')
        ->assertSet('submitted', true);

    expect(FreelancerApplication::count())->toBe(0);
});

it('throttles repeated submissions from the same address', function () {
    RateLimiter::clear('freelancer-application:127.0.0.1');

    foreach (range(1, 5) as $attempt) {
        fillApplication(Livewire::test('pages::freelancer.apply'), validApplication(['email' => "applicant{$attempt}@example.com"]))
            ->call('submit')
            ->assertHasNoErrors();
    }

    fillApplication(Livewire::test('pages::freelancer.apply'), validApplication(['email' => 'applicant6@example.com']))
        ->call('submit')
        ->assertHasErrors('email');

    expect(FreelancerApplication::count())->toBe(5);
});
