<?php

use App\Filament\Resources\BrandProfiles\BrandProfileResource;
use App\Filament\Resources\BrandProfiles\Pages\ListBrandProfiles;
use App\Filament\Resources\BrandProfiles\Pages\ViewBrandProfile;
use App\Filament\Resources\FreelancerProfiles\FreelancerProfileResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\BrandProfile;
use App\Models\Industry;
use App\Models\ProfessionalFunction;
use App\Models\ProfessionalRole;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

it('has its own page at /admin/brand-profiles', function () {
    $this->get('/admin/brand-profiles')->assertOk()->assertSee('Brand Profiles');
});

it('keeps everyone but admins out', function (string $role) {
    $user = User::factory()->{$role}()->create();

    $this->actingAs($user)->get('/admin/brand-profiles')->assertForbidden();
})->with(['client', 'freelancer']);

it('lists the brand profiles, newest first', function () {
    $older = BrandProfile::factory()->create(['created_at' => now()->subDays(3)]);
    $newer = BrandProfile::factory()->create(['created_at' => now()->subDay()]);

    Livewire::test(ListBrandProfiles::class)
        ->assertCanSeeTableRecords([$newer, $older], inOrder: true)
        ->assertSee($newer->company_name)
        ->assertSee($newer->user->email);
});

it('searches by company, account name and email', function () {
    $acme = BrandProfile::factory()->create(['company_name' => 'Acme Goods']);
    $other = BrandProfile::factory()->create(['company_name' => 'Zenith Foods']);

    Livewire::test(ListBrandProfiles::class)
        ->searchTable('Acme')
        ->assertCanSeeTableRecords([$acme])
        ->assertCanNotSeeTableRecords([$other])
        ->searchTable($other->user->email)
        ->assertCanSeeTableRecords([$other])
        ->assertCanNotSeeTableRecords([$acme]);
});

it('filters by plan and industry', function () {
    $industry = Industry::factory()->create();
    $paid = BrandProfile::factory()->for(User::factory()->client()->paid(), 'user')->create(['industry_id' => $industry->id]);
    $free = BrandProfile::factory()->create();

    Livewire::test(ListBrandProfiles::class)
        ->filterTable('plan', 'paid-plan')
        ->assertCanSeeTableRecords([$paid])
        ->assertCanNotSeeTableRecords([$free])
        ->removeTableFilter('plan')
        ->filterTable('plan', 'free-plan')
        ->assertCanSeeTableRecords([$free])
        ->assertCanNotSeeTableRecords([$paid])
        ->removeTableFilter('plan')
        ->filterTable('industry', $industry->id)
        ->assertCanSeeTableRecords([$paid])
        ->assertCanNotSeeTableRecords([$free]);
});

it('shows the whole brand profile on its page', function () {
    $function = ProfessionalFunction::factory()->create(['name' => 'Paid Media']);
    $role = ProfessionalRole::factory()->for($function, 'professionalFunction')->create(['name' => 'Growth Marketer']);
    $brand = BrandProfile::factory()->create([
        'company_name' => 'Acme Goods',
        'description' => 'We make everyday things.',
        'location' => 'Austin, United States',
        'website' => 'https://acme.test',
        'contact_name' => 'Wile Coyote',
        'contact_email' => 'hello@acme.test',
        'industry_id' => Industry::factory()->create(['name' => 'Home & Garden'])->id,
        'social_links' => ['linkedin' => 'https://linkedin.com/company/acme'],
    ]);
    $brand->professionalFunctions()->attach($function);
    $brand->professionalRoles()->attach($role);

    Livewire::test(ViewBrandProfile::class, ['record' => $brand->getKey()])
        ->assertSee('Acme Goods')
        ->assertSee('We make everyday things.')
        ->assertSee('Austin, United States')
        ->assertSee('https://acme.test')
        ->assertSee('Wile Coyote')
        ->assertSee('hello@acme.test')
        ->assertSee('Home &amp; Garden', false)
        ->assertSee('Paid Media')
        ->assertSee('Growth Marketer')
        ->assertSee('https://linkedin.com/company/acme')
        ->assertSee($brand->user->email);
});

it('cannot create, edit or delete a brand profile', function () {
    $brand = BrandProfile::factory()->create();

    expect(BrandProfileResource::canCreate())->toBeFalse();

    $this->get('/admin/brand-profiles/create')->assertNotFound();
    $this->get("/admin/brand-profiles/{$brand->id}/edit")->assertNotFound();
});

it('lists Freelancer Profiles first in the People group, then Brand Profiles and Users', function () {
    $resources = [
        FreelancerProfileResource::class,
        BrandProfileResource::class,
        UserResource::class,
    ];

    foreach ($resources as $resource) {
        expect($resource::getNavigationGroup())->toBe('People');
    }

    expect(collect($resources)->map(fn (string $resource): int => $resource::getNavigationSort())->all())->toBe([1, 2, 3]);
});
