<?php

use App\Filament\Widgets\CatalogStatsWidget;
use App\Filament\Widgets\PendingApplicationsWidget;
use App\Filament\Widgets\PendingProfileRevisionsWidget;
use App\Filament\Widgets\PeopleStatsWidget;
use App\Models\BrandProfile;
use App\Models\FreelancerApplication;
use App\Models\FreelancerProfile;
use App\Models\FreelancerProfileRevision;
use App\Models\Industry;
use App\Models\ProfessionalFunction;
use App\Models\ProfessionalRole;
use App\Models\Skill;
use App\Models\Tool;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

it('lists the four groups in order and nothing else', function () {
    $widgets = collect(Filament::getPanel('admin')->getWidgets())
        ->sortBy(fn (string $widget): int => $widget::getSort())
        ->values()
        ->all();

    expect($widgets)->toBe([
        PendingApplicationsWidget::class,
        PendingProfileRevisionsWidget::class,
        PeopleStatsWidget::class,
        CatalogStatsWidget::class,
    ]);
});

it('opens the dashboard for an admin', function () {
    $this->get('/admin')->assertOk();
});

it('shows the first three applications that are waiting for a decision', function () {
    $oldest = FreelancerApplication::factory()->count(3)->sequence(
        ['created_at' => now()->subDays(10)],
        ['created_at' => now()->subDays(9)],
        ['created_at' => now()->subDays(8)],
    )->create();
    $later = FreelancerApplication::factory()->create(['created_at' => now()->subDay()]);
    $decided = FreelancerApplication::factory()->approved()->create(['created_at' => now()->subDays(20)]);
    $rejected = FreelancerApplication::factory()->rejected()->create(['created_at' => now()->subDays(21)]);

    Livewire::test(PendingApplicationsWidget::class)
        ->assertSee('Freelancer Applications')
        ->assertCanSeeTableRecords($oldest, inOrder: true)
        ->assertCanNotSeeTableRecords([$later, $decided, $rejected]);
});

it('says so when no application is waiting', function () {
    FreelancerApplication::factory()->approved()->create();

    Livewire::test(PendingApplicationsWidget::class)
        ->assertSee('No applications are waiting');
});

it('shows the first three profile revisions that are waiting for review', function () {
    $oldest = FreelancerProfileRevision::factory()->count(3)->pendingReview()->sequence(
        ['submitted_at' => now()->subDays(10)],
        ['submitted_at' => now()->subDays(9)],
        ['submitted_at' => now()->subDays(8)],
    )->create();
    $later = FreelancerProfileRevision::factory()->pendingReview()->create(['submitted_at' => now()->subDay()]);
    $draft = FreelancerProfileRevision::factory()->draft()->create();
    $changesRequested = FreelancerProfileRevision::factory()->changesRequested()->create();

    Livewire::test(PendingProfileRevisionsWidget::class)
        ->assertSee('Profile Revisions')
        ->assertCanSeeTableRecords($oldest, inOrder: true)
        ->assertCanNotSeeTableRecords([$later, $draft, $changesRequested]);
});

it('says so when no revision is waiting', function () {
    FreelancerProfileRevision::factory()->draft()->create();

    Livewire::test(PendingProfileRevisionsWidget::class)
        ->assertSee('No revisions are waiting for review');
});

it('counts freelancers and brands, and how many of them are active', function () {
    User::factory()->freelancer()->count(5)->create();
    User::factory()->freelancer()->suspended()->create();
    User::factory()->freelancer()->deactivated()->create();
    User::factory()->freelancer()->create()->delete();

    User::factory()->client()->count(3)->create();
    User::factory()->client()->restricted()->create();

    Livewire::test(PeopleStatsWidget::class)
        ->assertSeeInOrder(['Freelancers', '7', '5 active', 'Brands', '4', '3 active']);
});

it('counts every catalog list, with the entries that are in use', function () {
    $functions = ProfessionalFunction::factory()->count(4)->create();

    $roles = ProfessionalRole::factory()->count(6)->for($functions[1], 'professionalFunction')->create();
    $skills = Skill::factory()->count(7)->create();
    $tools = Tool::factory()->count(8)->create();
    $industries = Industry::factory()->count(9)->create();

    $first = FreelancerProfile::factory()->create(['professional_function_id' => $functions[2]->id, 'main_role_id' => $roles[0]->id, 'secondary_role_id' => $roles[1]->id]);
    $second = FreelancerProfile::factory()->create(['professional_function_id' => $functions[2]->id, 'main_role_id' => $roles[1]->id, 'third_role_id' => $roles[2]->id]);

    foreach ([$first, $second] as $profile) {
        $profile->skills()->attach([$skills[0]->id, $skills[1]->id]);
        $profile->tools()->attach($tools[0]->id);
    }
    $first->industries()->attach([$industries[0]->id, $industries[1]->id, $industries[2]->id]);

    Livewire::test(CatalogStatsWidget::class)
        ->assertSeeInOrder([
            'Functions', '4', '1 in use',
            'Roles', '6', '3 in use',
            'Skills', '7', '2 in use',
            'Tools', '8', '1 in use',
            'Industries', '9', '3 in use',
        ]);
});

it('leaves brands that never filled in a profile in the brand count', function () {
    BrandProfile::factory()->create();
    User::factory()->client()->create();

    Livewire::test(PeopleStatsWidget::class)
        ->assertSeeInOrder(['Brands', '2', '2 active']);
});

it('orders the navigation groups: dashboard, People, Moderation, Catalog, System', function () {
    $this->actingAs(User::factory()->admin()->create());

    $groups = collect(Filament::getPanel('admin')->getNavigation())
        ->map(fn ($group) => $group->getLabel())->values()
        ->all();

    expect($groups)->toBe([null, 'People', 'Moderation', 'Catalog', 'System']);
});
