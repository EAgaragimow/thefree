<?php

use App\Actions\Profiles\ProfileContent;
use App\Models\FreelancerProfile;
use App\Models\Industry;
use App\Models\ProfessionalFunction;
use App\Models\ProfessionalRole;
use App\Models\Skill;
use App\Models\Tool;
use App\Models\User;
use Database\Seeders\DemoFreelancerSeeder;
use Illuminate\Support\Facades\Hash;

function seedDemoFreelancers(): void
{
    ProfessionalFunction::factory()->count(3)->create()
        ->each(fn (ProfessionalFunction $function) => ProfessionalRole::factory()->count(3)->for($function, 'professionalFunction')->create());
    Skill::factory()->count(20)->create();
    Tool::factory()->count(25)->create();
    Industry::factory()->count(12)->create();

    app()->detectEnvironment(fn () => 'local');

    test()->seed(DemoFreelancerSeeder::class);
}

it('creates fifty published freelancers with incremental emails and one shared password', function () {
    seedDemoFreelancers();

    $users = User::query()->where('role', 'freelancer')->orderBy('email')->get();

    expect($users)->toHaveCount(50)
        ->and($users->first()->email)->toBe('freelancer+001@gmail.com')
        ->and($users->last()->email)->toBe('freelancer+050@gmail.com')
        ->and($users->pluck('name')->unique())->toHaveCount(50)
        ->and(Hash::check('freelancer', $users->first()->password))->toBeTrue()
        ->and($users->every(fn (User $user): bool => $user->hasVerifiedEmail()))->toBeTrue()
        ->and(FreelancerProfile::query()->visible()->count())->toBe(50)
        ->and(FreelancerProfile::query()->pluck('slug')->unique())->toHaveCount(50);
});

it('makes exactly ten of them newbies and the rest older than a month', function () {
    seedDemoFreelancers();

    $newbies = FreelancerProfile::query()->where('is_newbie', true)->get();
    $others = FreelancerProfile::query()->where('is_newbie', false)->get();

    expect($newbies)->toHaveCount(10)
        ->and($newbies->every(fn (FreelancerProfile $profile): bool => $profile->created_at->greaterThan(now()->subMonth())))->toBeTrue()
        ->and($others)->toHaveCount(40)
        ->and($others->every(fn (FreelancerProfile $profile): bool => $profile->created_at->lessThan(now()->subMonth())))->toBeTrue()
        ->and($others->every(fn (FreelancerProfile $profile): bool => $profile->user->created_at->lessThan(now()->subMonth())))->toBeTrue();

    $this->artisan('app:expire-newbie-profiles')->assertSuccessful();

    expect(FreelancerProfile::query()->where('is_newbie', true)->count())->toBe(10);
});

it('spreads scores from nothing to a hundred thousand', function () {
    seedDemoFreelancers();

    $scores = FreelancerProfile::query()->pluck('score');

    expect($scores->min())->toBe(0)
        ->and($scores->max())->toBe(100000)
        ->and($scores->unique()->count())->toBeGreaterThan(40);
});

it('fills in every profile within the profile rules', function () {
    seedDemoFreelancers();

    FreelancerProfile::query()->with(['skills', 'tools', 'industries', 'mainRole', 'secondaryRole', 'thirdRole', 'services', 'consultationOffers'])->get()
        ->each(function (FreelancerProfile $profile): void {
            $roles = array_filter([$profile->mainRole, $profile->secondaryRole, $profile->thirdRole]);

            expect(mb_strlen($profile->about))->toBeLessThanOrEqual(200)
                ->and($profile->professional_title)->not->toBeEmpty()
                ->and($profile->professionalFunction)->not->toBeNull()
                ->and($roles)->not->toBeEmpty()
                ->and(collect($roles)->every(fn ($role): bool => $role->professional_function_id === $profile->professional_function_id))->toBeTrue()
                ->and($profile->skills->count())->toBeBetween(ProfileContent::MIN_SKILLS, ProfileContent::MAX_SKILLS)
                ->and($profile->tools->count())->toBeBetween(ProfileContent::MIN_TOOLS, ProfileContent::MAX_TOOLS)
                ->and($profile->industries->count())->toBeLessThanOrEqual(ProfileContent::MAX_INDUSTRIES)
                ->and($profile->hourly_rate_cents)->toBeGreaterThan(0)
                ->and($profile->services->count())->toBeBetween(1, ProfileContent::MAX_SERVICES)
                ->and($profile->consultationOffers->pluck('type.value')->sort()->values()->all())->toBe(['free', 'paid'])
                ->and(ProfileContent::fromProfile($profile)->offerProblems())->toBe([])
                ->and(ProfileContent::fromProfile($profile)->detailProblems())->toBe([]);
        });
});

it('updates the same accounts when it runs again', function () {
    seedDemoFreelancers();

    $this->seed(DemoFreelancerSeeder::class);

    expect(User::query()->where('role', 'freelancer')->count())->toBe(50)
        ->and(FreelancerProfile::query()->count())->toBe(50)
        ->and(FreelancerProfile::query()->where('is_newbie', true)->count())->toBe(10);
});

it('refuses to run outside the local environment', function (string $environment) {
    ProfessionalFunction::factory()->create();
    app()->detectEnvironment(fn () => $environment);

    expect(fn () => (new DemoFreelancerSeeder)->run())
        ->toThrow(RuntimeException::class, 'only seeded in the local environment');

    expect(User::query()->count())->toBe(0);
})->with(['production', 'staging', 'testing']);

it('asks for the catalog when it is empty', function () {
    app()->detectEnvironment(fn () => 'local');

    expect(fn () => $this->seed(DemoFreelancerSeeder::class))
        ->toThrow(RuntimeException::class, 'The catalog is empty');
});
