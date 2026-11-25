<?php

use App\Enums\BillingState;
use App\Enums\SubscriptionType;
use App\Models\AuditLog;
use App\Models\BrandProfile;
use App\Models\Industry;
use App\Models\ProfessionalFunction;
use App\Models\ProfessionalRole;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

it('creates a private brand profile with interests', function () {
    $user = User::factory()->client()->create();
    $functions = ProfessionalFunction::factory()->count(2)->create();
    $role = ProfessionalRole::factory()->for($functions[0], 'professionalFunction')->create();
    $industry = Industry::factory()->create(['name' => 'Manufacturing']);

    Livewire::actingAs($user)
        ->test('pages::client.brand-profile')
        ->set('company_name', 'Acme Inc.')
        ->set('description', 'We make everything.')
        ->set('industry_id', (string) $industry->id)
        ->set('website', 'https://acme.test')
        ->set('linkedin', 'https://linkedin.com/company/acme')
        ->set('contact_email', 'hello@acme.test')
        ->set('function_ids', $functions->pluck('id')->map(fn ($id) => (string) $id)->all())
        ->set('role_ids', [(string) $role->id])
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('saved', true);

    $profile = $user->fresh()->brandProfile;

    expect($profile->company_name)->toBe('Acme Inc.')
        ->and($profile->industry_id)->toBe($industry->id)
        ->and($profile->social_links)->toBe(['linkedin' => 'https://linkedin.com/company/acme'])
        ->and($profile->professionalFunctions->pluck('id')->all())->toEqualCanonicalizing($functions->pluck('id')->all())
        ->and($profile->professionalRoles->pluck('id')->all())->toBe([$role->id]);
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

it('rejects a function that does not exist', function () {
    Livewire::actingAs(User::factory()->client()->create())
        ->test('pages::client.brand-profile')
        ->set('company_name', 'Acme')
        ->set('function_ids', ['9999'])
        ->call('save')
        ->assertHasErrors('function_ids.0');
});

it('lists only the roles of the selected functions, grouped by function', function () {
    $paid = ProfessionalFunction::factory()->create(['name' => 'Paid Media', 'sort_order' => 1]);
    $creative = ProfessionalFunction::factory()->create(['name' => 'Creative', 'sort_order' => 2]);
    $paidRole = ProfessionalRole::factory()->for($paid, 'professionalFunction')->create(['name' => 'Paid Social Marketer']);
    ProfessionalRole::factory()->for($creative, 'professionalFunction')->create(['name' => 'Copywriter']);

    Livewire::actingAs(User::factory()->client()->create())
        ->test('pages::client.brand-profile')
        ->assertSet('function_ids', [])
        ->assertDontSee('Paid Social Marketer')
        ->set('function_ids', [(string) $paid->id])
        ->assertSee('Paid Social Marketer')
        ->assertDontSee('Copywriter')
        ->assertSee('Paid Media')
        ->assertSet('function_ids', [(string) $paid->id])
        ->set('role_ids', [(string) $paidRole->id])
        ->assertSet('role_ids', [(string) $paidRole->id]);
});

it('drops a selected role when its function is deselected', function () {
    $paid = ProfessionalFunction::factory()->create();
    $creative = ProfessionalFunction::factory()->create();
    $paidRole = ProfessionalRole::factory()->for($paid, 'professionalFunction')->create();
    $creativeRole = ProfessionalRole::factory()->for($creative, 'professionalFunction')->create();

    Livewire::actingAs(User::factory()->client()->create())
        ->test('pages::client.brand-profile')
        ->set('function_ids', [(string) $paid->id, (string) $creative->id])
        ->set('role_ids', [(string) $paidRole->id, (string) $creativeRole->id])
        ->set('function_ids', [(string) $creative->id])
        ->assertSet('role_ids', [(string) $creativeRole->id]);
});

it('rejects a role that does not belong to a selected function', function () {
    $paid = ProfessionalFunction::factory()->create();
    $otherRole = ProfessionalRole::factory()->create();

    Livewire::actingAs(User::factory()->client()->create())
        ->test('pages::client.brand-profile')
        ->set('company_name', 'Acme')
        ->set('function_ids', [(string) $paid->id])
        ->set('role_ids', [(string) $otherRole->id])
        ->call('save')
        ->assertHasErrors('role_ids.0');

    expect(BrandProfile::count())->toBe(0);
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

it('only accepts an industry from the catalog', function () {
    $user = User::factory()->client()->create();

    Livewire::actingAs($user)
        ->test('pages::client.brand-profile')
        ->set('company_name', 'Acme Inc.')
        ->set('industry_id', '9999')
        ->call('save')
        ->assertHasErrors('industry_id');

    expect(BrandProfile::count())->toBe(0);
});

it('lets a brand switch its plan type in the local environment', function () {
    $user = User::factory()->client()->paymentRestricted()->create();
    app()->detectEnvironment(fn () => 'local');

    $component = Livewire::actingAs($user)
        ->test('pages::client.brand-profile')
        ->assertSee('Plan Type')
        ->assertSet('plan_type', 'paid-plan')
        ->set('plan_type', 'free-plan')
        ->assertSee('Your plan is now Free.');

    expect($user->refresh()->subscription_type)->toBe(SubscriptionType::FreePlan)
        ->and($user->billing_state)->toBe(BillingState::Ok);

    $component->set('plan_type', 'paid-plan');

    expect($user->refresh()->subscription_type)->toBe(SubscriptionType::PaidPlan)
        ->and($user->hasPaidAccess())->toBeTrue()
        ->and(AuditLog::where('action', 'user.plan_switched')->count())->toBe(2);
});

it('rejects an unknown plan type', function () {
    $user = User::factory()->client()->create();
    app()->detectEnvironment(fn () => 'local');

    Livewire::actingAs($user)
        ->test('pages::client.brand-profile')
        ->set('plan_type', 'enterprise')
        ->assertHasErrors('plan_type');

    expect($user->refresh()->subscription_type)->toBe(SubscriptionType::FreePlan);
});

it('neither offers nor honours the plan switch outside the local environment', function (string $environment) {
    $user = User::factory()->client()->create();
    app()->detectEnvironment(fn () => $environment);

    Livewire::actingAs($user)
        ->test('pages::client.brand-profile')
        ->assertDontSee('Plan Type')
        ->set('plan_type', 'paid-plan')
        ->assertForbidden();

    expect($user->refresh()->subscription_type)->toBe(SubscriptionType::FreePlan);
})->with(['production', 'staging', 'testing']);
