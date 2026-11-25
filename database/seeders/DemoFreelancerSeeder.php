<?php

namespace Database\Seeders;

use App\Actions\Profiles\ProfileContent;
use App\Enums\ConsultationType;
use App\Enums\PricingMode;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\WeeklyHours;
use App\Models\FreelancerProfile;
use App\Models\Industry;
use App\Models\ProfessionalFunction;
use App\Models\Skill;
use App\Models\Tool;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Fifty published demo freelancers for trying out the directory: `freelancer+001@gmail.com` to
 * `freelancer+050@gmail.com`, all with the password "freelancer". They use the catalog that is already
 * in the database (run `php artisan db:seed` first) and differ in function, roles, skills, tools,
 * industries, rates and score. Ten are newbies; the rest were created more than a month ago.
 *
 * Local environment only, because the accounts share a known password. Running it again updates the
 * same fifty accounts instead of adding more. It is not part of DatabaseSeeder:
 * `php artisan db:seed --class=DemoFreelancerSeeder`.
 */
class DemoFreelancerSeeder extends Seeder
{
    public const COUNT = 50;

    public const NEWBIES = 10;

    public const PASSWORD = 'freelancer';

    public const MAX_SCORE = 100000;

    private const FIRST_NAMES = [
        'Olivia', 'Mateo', 'Sofia', 'Liam', 'Amara', 'Noah', 'Yuki', 'Lucas', 'Priya', 'Elena',
        'Daniel', 'Fatima', 'Henrik', 'Isabela', 'Kofi', 'Marta', 'Omar', 'Hannah', 'Ravi', 'Chloe',
        'Andrii', 'Nadia', 'Tomas', 'Leila', 'Gabriel', 'Ingrid', 'Samuel', 'Mei', 'Dmytro', 'Camille',
        'Jonas', 'Aisha', 'Diego', 'Katarzyna', 'Ethan', 'Zainab', 'Felix', 'Valentina', 'Arjun', 'Emma',
        'Luka', 'Natalia', 'Oscar', 'Tara', 'Viktor', 'Grace', 'Pedro', 'Anika', 'Michael', 'Layla',
    ];

    private const LAST_NAMES = [
        'Anderson', 'Rodriguez', 'Kowalski', 'Nguyen', 'Okafor', 'Müller', 'Tanaka', 'Silva', 'Sharma', 'Petrova',
        'Thompson', 'Haddad', 'Lindqvist', 'Costa', 'Mensah', 'Novak', 'Hassan', 'Fischer', 'Patel', 'Dubois',
        'Shevchenko', 'Rossi', 'Horvat', 'Karimi', 'Moreau', 'Johansen', 'Bennett', 'Zhang', 'Kovalenko', 'Laurent',
        'Becker', 'Abdullah', 'Fernandez', 'Wiśniewska', 'Carter', 'Bello', 'Weber', 'Bianchi', 'Mehta', 'Walsh',
        'Jovanovic', 'Sokolova', 'Eriksen', 'Gupta', 'Marin', 'Bailey', 'Santos', 'Singh', 'Brooks', 'Nasser',
    ];

    /** @var list<array{0: string, 1: string}> Location and time zone. */
    private const PLACES = [
        ['Lisbon, Portugal', 'Europe/Lisbon'], ['Berlin, Germany', 'Europe/Berlin'], ['Warsaw, Poland', 'Europe/Warsaw'],
        ['Kyiv, Ukraine', 'Europe/Kyiv'], ['London, United Kingdom', 'Europe/London'], ['Barcelona, Spain', 'Europe/Madrid'],
        ['Amsterdam, Netherlands', 'Europe/Amsterdam'], ['Stockholm, Sweden', 'Europe/Stockholm'], ['Tallinn, Estonia', 'Europe/Tallinn'],
        ['Austin, United States', 'America/Chicago'], ['New York, United States', 'America/New_York'], ['Denver, United States', 'America/Denver'],
        ['Los Angeles, United States', 'America/Los_Angeles'], ['Toronto, Canada', 'America/Toronto'], ['Mexico City, Mexico', 'America/Mexico_City'],
        ['São Paulo, Brazil', 'America/Sao_Paulo'], ['Buenos Aires, Argentina', 'America/Argentina/Buenos_Aires'], ['Bogotá, Colombia', 'America/Bogota'],
        ['Lagos, Nigeria', 'Africa/Lagos'], ['Nairobi, Kenya', 'Africa/Nairobi'], ['Dubai, United Arab Emirates', 'Asia/Dubai'],
        ['Bangalore, India', 'Asia/Kolkata'], ['Singapore, Singapore', 'Asia/Singapore'], ['Manila, Philippines', 'Asia/Manila'],
        ['Sydney, Australia', 'Australia/Sydney'],
    ];

    private const COMPANIES = [
        'Northwind Studio', 'Brightpath Labs', 'Fieldnote', 'Lumen & Co', 'Harbor Goods', 'Tidewater Foods', 'Pinecone Agency',
        'Orbit Commerce', 'Marlow & Finch', 'Copperleaf', 'Skyline Apparel', 'Quartz Beauty', 'Bluebird Media', 'Cedar & Stone',
        'Nimbus Software', 'Wildflower Home', 'Atlas Outdoor', 'Kindred Pets',
    ];

    private const ABOUT_TEMPLATES = [
        '{role} with {years} years of experience in {industries}. I help brands turn ideas into measurable growth.',
        'Independent {role} focused on {industries}. {years}+ years with DTC and B2B teams, from strategy to hands-on execution.',
        '{years} years as a {role}. I have worked across {industries} and like clear briefs, fast feedback and honest reporting.',
        'Hands-on {role} for {industries} brands. I join as a fractional partner, set things up and leave your team stronger.',
        '{role} who has shipped work for {industries} companies for {years} years. Practical, data-minded and easy to work with.',
    ];

    private const BEST_FIT = [
        'Fast-growing DTC brands preparing for a launch',
        'Teams without an in-house specialist',
        'Brands scaling past their first $1M in revenue',
        'Agencies that need a senior specialist on demand',
        'Marketplace and retail sellers expanding to new channels',
        'Founders who want a clear plan before hiring',
        'Established brands that want a fresh outside view',
    ];

    /** @var list<array{title: string, description: string, mode: PricingMode, min: int, max: int}> Prices in dollars. */
    private const SERVICES = [
        ['title' => 'Channel audit', 'description' => 'A written review of your current setup with the five changes that will matter most.', 'mode' => PricingMode::Fixed, 'min' => 600, 'max' => 1800],
        ['title' => 'Monthly retainer', 'description' => 'Ongoing hands-on support: weekly calls, reporting and execution.', 'mode' => PricingMode::StartingFrom, 'min' => 1500, 'max' => 6000],
        ['title' => 'Launch plan', 'description' => 'A 30-day plan for a new product or campaign, with owners, budget and milestones.', 'mode' => PricingMode::Fixed, 'min' => 900, 'max' => 3000],
        ['title' => 'Team workshop', 'description' => 'A half-day workshop for your team, with templates you can reuse afterwards.', 'mode' => PricingMode::StartingFrom, 'min' => 800, 'max' => 2500],
        ['title' => 'Custom project', 'description' => 'Tell me what you need and I will come back with scope, price and timeline.', 'mode' => PricingMode::CustomQuote, 'min' => 0, 'max' => 0],
    ];

    private const HIGHLIGHTS = [
        ['Relaunch for a skincare startup', 'Rebuilt the offer and the funnel in eight weeks. Repeat purchases grew by a third in the first quarter.'],
        ['Q4 growth campaign', 'Planned and ran a seasonal campaign across three channels, with weekly reporting for the founders.'],
        ['Store migration and speed-up', 'Moved a 2,000-product store to a new platform with no loss in rankings and a much faster checkout.'],
        ['Lifecycle programme rebuild', 'Replaced ad-hoc sends with a simple lifecycle programme that now drives a large share of revenue.'],
        ['Creator partnership programme', 'Set up the process, contracts and tracking for a creator programme that grew from 5 to 60 partners.'],
        ['Team playbook', 'Documented the weekly operating rhythm for a remote team so new hires ramp up in days, not weeks.'],
    ];

    /**
     * @throws RuntimeException
     */
    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('The demo freelancers share a known password, so they are only seeded in the local environment.');
        }

        $functions = ProfessionalFunction::query()->where('is_active', true)->with('roles')->get()
            ->filter(fn (ProfessionalFunction $function): bool => $function->roles->isNotEmpty())
            ->values();
        $skillIds = Skill::query()->pluck('id')->all();
        $toolIds = Tool::query()->pluck('id')->all();
        $industries = Industry::query()->pluck('name', 'id')->all();

        if ($functions->isEmpty() || count($skillIds) < ProfileContent::MIN_SKILLS || count($toolIds) < ProfileContent::MIN_TOOLS) {
            throw new RuntimeException('The catalog is empty. Run `php artisan db:seed` first so there are functions, roles, skills and tools to choose from.');
        }

        // The same numbers every run, so running the seeder again updates the same accounts the same way.
        mt_srand(20261125);

        $password = Hash::make(self::PASSWORD);
        $newbies = array_slice($this->shuffle(range(0, self::COUNT - 1)), 0, self::NEWBIES);

        for ($index = 0; $index < self::COUNT; $index++) {
            DB::transaction(fn () => $this->seedFreelancer(
                $index,
                in_array($index, $newbies, true),
                $password,
                $functions->all(),
                $skillIds,
                $toolIds,
                $industries,
            ));
        }
    }

    /**
     * @param  list<ProfessionalFunction>  $functions
     * @param  list<int>  $skillIds
     * @param  list<int>  $toolIds
     * @param  array<int, string>  $industries  Names by id.
     */
    private function seedFreelancer(int $index, bool $newbie, string $password, array $functions, array $skillIds, array $toolIds, array $industries): void
    {
        $name = self::FIRST_NAMES[$index].' '.self::LAST_NAMES[($index * 13) % count(self::LAST_NAMES)];
        [$location, $timezone] = self::PLACES[($index * 7 + 3) % count(self::PLACES)];

        $created = $newbie
            ? CarbonImmutable::now()->subDays($this->between(1, 27))->subMinutes($this->between(0, 1000))
            : CarbonImmutable::now()->subDays($this->between(35, 730))->subMinutes($this->between(0, 1000));
        $published = min($created->addHours($this->between(2, 72)), CarbonImmutable::now()->subHour());

        $function = $functions[$this->between(0, count($functions) - 1)];
        $roles = array_slice($this->shuffle($function->roles->all()), 0, $this->between(1, min(3, $function->roles->count())));
        $years = $this->between(2, 18);

        $industryIds = array_slice($this->shuffle(array_keys($industries)), 0, min(count($industries), $this->between(0, 6)));
        $industryNames = array_map(fn (int $id): string => $industries[$id], $industryIds);

        $email = sprintf('freelancer+%03d@gmail.com', $index + 1);
        $slug = Str::slug($name);
        $mainRole = $roles[0]->name;

        $user = User::query()->firstOrNew(['email' => $email]);
        $user->forceFill([
            'name' => $name,
            'password' => $password,
            'timezone' => $timezone,
            'role' => UserRole::Freelancer,
            'status' => UserStatus::Active,
            'email_verified_at' => $created,
            'created_at' => $created,
            'updated_at' => $created,
        ])->save();

        $retainers = $this->between(0, 2) > 0;

        $profile = FreelancerProfile::query()->firstOrNew(['user_id' => $user->id]);
        $profile->forceFill([
            'professional_title' => $this->pick(['', 'Senior ', 'Lead ', 'Freelance ', 'Principal ']).$mainRole,
            'about' => $this->about($mainRole, $years, $industryNames),
            'location' => $location,
            'timezone' => $timezone,
            'website' => $this->between(0, 1) === 1 ? "https://{$slug}.example.com" : null,
            'social_links' => ['linkedin' => "https://www.linkedin.com/in/{$slug}"],
            'professional_function_id' => $function->id,
            'main_role_id' => $roles[0]->id,
            'secondary_role_id' => $roles[1]->id ?? null,
            'third_role_id' => $roles[2]->id ?? null,
            'hourly_rate_cents' => $this->between(25, 180) * 100,
            'offers_monthly_retainers' => $retainers,
            'monthly_retainers_description' => $retainers ? "Fixed monthly scope with a weekly call, reporting and hands-on {$function->name} work." : null,
            'monthly_retainers_from_cents' => $retainers ? $this->between(15, 60) * 10000 : null,
            'weekly_hours' => $this->pick(WeeklyHours::cases()),
            'open_to_full_time' => $this->between(0, 2) === 0,
            'best_fit' => array_slice($this->shuffle(self::BEST_FIT), 0, $this->between(1, 3)),
            'score' => $this->score($index),
            'is_newbie' => $newbie,
            'published_at' => $published,
            'content_updated_at' => $published,
            'created_at' => $created,
            'updated_at' => $published,
        ])->save();

        $profile->skills()->sync($this->sample($skillIds, ProfileContent::MIN_SKILLS, ProfileContent::MAX_SKILLS));
        $profile->tools()->sync($this->sample($toolIds, ProfileContent::MIN_TOOLS, 12));
        $profile->industries()->sync($industryIds);

        $this->seedExperiences($profile, $mainRole, $years, $created);
        $this->seedServices($profile);
        $this->seedHighlights($profile);
        $this->seedConsultations($profile, $function->name);
    }

    /**
     * @param  list<string>  $industryNames
     */
    private function about(string $role, int $years, array $industryNames): string
    {
        $industries = match (count($industryNames)) {
            0 => 'consumer brands',
            1 => $industryNames[0],
            default => $industryNames[0].' and '.$industryNames[1],
        };

        $text = str_replace(
            ['{role}', '{years}', '{industries}'],
            [$role, (string) $years, $industries],
            self::ABOUT_TEMPLATES[$this->between(0, count(self::ABOUT_TEMPLATES) - 1)],
        );

        return Str::limit($text, 200, '');
    }

    /**
     * The first two get the lowest and the highest possible score; the rest lean towards the low end,
     * as real scores do.
     */
    private function score(int $index): int
    {
        return match ($index) {
            0 => 0,
            1 => self::MAX_SCORE,
            default => (int) round(self::MAX_SCORE * (mt_rand() / mt_getrandmax()) ** 2),
        };
    }

    private function seedExperiences(FreelancerProfile $profile, string $role, int $years, CarbonImmutable $created): void
    {
        $profile->experiences()->reorder()->delete();

        $count = $this->between(1, 3);
        $companies = array_slice($this->shuffle(self::COMPANIES), 0, $count);
        $end = $created->subMonths($this->between(0, 2))->startOfMonth();

        foreach ($companies as $position => $company) {
            $started = $end->subMonths($this->between(12, 40));
            $isCurrent = $position === 0;

            $profile->experiences()->create([
                'company' => $company,
                'role' => $role,
                'started_at' => $started->toDateString(),
                'ended_at' => $isCurrent ? null : $end->toDateString(),
                'description' => $isCurrent
                    ? "Lead the {$role} work end to end, from planning to weekly reporting."
                    : 'Owned results for the channel and trained two junior teammates.',
                'sort_order' => $position,
            ]);

            $end = $started->subMonths($this->between(0, 3));
        }
    }

    private function seedServices(FreelancerProfile $profile): void
    {
        $profile->services()->reorder()->forceDelete();

        foreach (array_slice($this->shuffle(self::SERVICES), 0, $this->between(1, ProfileContent::MAX_SERVICES)) as $position => $service) {
            $profile->services()->create([
                'title' => $service['title'],
                'description' => $service['description'],
                'pricing_mode' => $service['mode'],
                'price_cents' => $service['mode']->requiresPrice() ? $this->between($service['min'] / 100, $service['max'] / 100) * 10000 : null,
                'sort_order' => $position,
            ]);
        }
    }

    private function seedHighlights(FreelancerProfile $profile): void
    {
        $profile->highlights()->reorder()->delete();

        foreach (array_slice($this->shuffle(self::HIGHLIGHTS), 0, $this->between(0, 3)) as $position => [$title, $description]) {
            $profile->highlights()->create([
                'title' => $title,
                'description' => $description,
                'gallery' => [],
                'video_urls' => [],
                'links' => [],
                'sort_order' => $position,
            ]);
        }
    }

    private function seedConsultations(FreelancerProfile $profile, string $functionName): void
    {
        $profile->consultationOffers()->reorder()->forceDelete();

        $profile->consultationOffers()->create([
            'type' => ConsultationType::Free,
            'is_enabled' => $this->between(0, 3) > 0,
            'duration_minutes' => $this->pick([15, 20, 30]),
            'price_cents' => 0,
            'description' => 'A short intro call to see whether we are a good fit.',
            'availability' => $this->windows(2, 3, '10:00', '16:00'),
        ]);

        $profile->consultationOffers()->create([
            'type' => ConsultationType::Paid,
            'is_enabled' => $this->between(0, 6) > 0,
            'duration_minutes' => $this->pick([30, 45, 60]),
            'price_cents' => $this->between(1, 10) * 2500,
            'description' => "A working session on your {$functionName} questions, with notes and next steps afterwards.",
            'availability' => $this->windows(3, 5, '09:00', '17:00'),
        ]);
    }

    /**
     * One window per day on a random selection of weekdays, in the order of the week.
     *
     * @return list<array{day: string, start: string, end: string}>
     */
    private function windows(int $fewest, int $most, string $start, string $end): array
    {
        $days = array_slice($this->shuffle(['mon', 'tue', 'wed', 'thu', 'fri']), 0, $this->between($fewest, $most));
        $days = array_values(array_intersect(['mon', 'tue', 'wed', 'thu', 'fri'], $days));

        return array_map(fn (string $day): array => ['day' => $day, 'start' => $start, 'end' => $end], $days);
    }

    /**
     * Between `$fewest` and `$most` distinct entries, picked at random.
     *
     * @param  list<int>  $items
     * @return list<int>
     */
    private function sample(array $items, int $fewest, int $most): array
    {
        $count = min(count($items), $this->between($fewest, $most));

        return array_slice($this->shuffle($items), 0, $count);
    }

    /**
     * @template T
     *
     * @param  non-empty-list<T>  $items
     * @return T
     */
    private function pick(array $items): mixed
    {
        return $items[mt_rand(0, count($items) - 1)];
    }

    private function between(int|float $fewest, int|float $most): int
    {
        return mt_rand((int) $fewest, (int) $most);
    }

    /**
     * Seeded counterpart of shuffle(), so the result is the same on every run.
     *
     * @template T
     *
     * @param  list<T>  $items
     * @return list<T>
     */
    private function shuffle(array $items): array
    {
        for ($i = count($items) - 1; $i > 0; $i--) {
            $j = mt_rand(0, $i);
            [$items[$i], $items[$j]] = [$items[$j], $items[$i]];
        }

        return $items;
    }
}
