<?php

use App\Models\Industry;
use App\Models\ProfessionalFunction;
use App\Models\ProfessionalRole;
use App\Models\Skill;
use App\Models\Tool;
use Database\Seeders\IndustrySeeder;
use Database\Seeders\ProfessionalFunctionSeeder;
use Database\Seeders\ProfessionalRoleSeeder;
use Database\Seeders\SkillSeeder;
use Database\Seeders\ToolSeeder;
use Illuminate\Support\Str;

it('seeds the freelancer catalogs with the agreed entries', function () {
    $this->seed([
        ProfessionalFunctionSeeder::class,
        ProfessionalRoleSeeder::class,
        SkillSeeder::class,
        ToolSeeder::class,
        IndustrySeeder::class,
    ]);

    expect(ProfessionalFunction::count())->toBe(12)
        ->and(ProfessionalRole::count())->toBe(42)
        ->and(Skill::count())->toBe(163)
        ->and(Tool::count())->toBe(315)
        ->and(Industry::count())->toBe(43)
        ->and(ProfessionalFunction::pluck('name')->all())->toContain('AI & Technology', 'Retention & CRM')
        ->and(ProfessionalRole::where('name', 'Marketer')->exists())->toBeFalse()
        ->and(Skill::where('slug', Str::slug('A/B Testing'))->value('name'))->toBe('A/B Testing')
        ->and(Skill::where('name', 'Meta Ads (Facebook & Instagram)')->exists())->toBeTrue();
});

it('puts every role under its function', function () {
    $this->seed(ProfessionalRoleSeeder::class);

    $paidMedia = ProfessionalFunction::where('name', 'Paid Media')->firstOrFail();

    expect(ProfessionalRole::whereNull('professional_function_id')->count())->toBe(0)
        ->and($paidMedia->roles->pluck('name')->all())->toBe(['Growth Marketer', 'Paid Search Marketer', 'Paid Social Marketer', 'Performance Creative Lead', 'Programmatic Marketer'])
        ->and(ProfessionalRole::where('name', 'Affiliate Marketer')->firstOrFail()->professionalFunction->name)->toBe('Influencer, Affiliate & PR')
        ->and(ProfessionalRole::where('name', 'Strategic Finance Lead')->firstOrFail()->professionalFunction->name)->toBe('Finance');
});

it('can be seeded again without duplicating entries', function () {
    $this->seed(SkillSeeder::class);
    $this->seed(SkillSeeder::class);

    expect(Skill::count())->toBe(163);
});
