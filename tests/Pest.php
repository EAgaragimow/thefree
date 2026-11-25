<?php

use App\Models\FreelancerProfile;
use Database\Factories\FreelancerProfileRevisionFactory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(LazilyRefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Give a live profile every catalog selection and answer needed to submit it for review.
 */
function completeLiveProfile(FreelancerProfile $profile): FreelancerProfile
{
    $details = FreelancerProfileRevisionFactory::completeDetails();

    $profile->forceFill([
        'professional_function_id' => $details['professional_function_id'],
        'main_role_id' => $details['role_ids'][0],
        'hourly_rate_cents' => $details['hourly_rate_cents'],
        'offers_monthly_retainers' => $details['offers_monthly_retainers'],
        'weekly_hours' => $details['weekly_hours'],
        'open_to_full_time' => $details['open_to_full_time'],
    ])->save();

    $profile->skills()->sync($details['skill_ids']);
    $profile->tools()->sync($details['tool_ids']);

    return $profile;
}
