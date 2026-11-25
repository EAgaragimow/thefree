<?php

namespace Database\Seeders;

use App\Models\ProfessionalFunction;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProfessionalFunctionSeeder extends Seeder
{
    /**
     * Seed the functions a freelancer can work in. Safe to run again: entries are matched by slug.
     */
    public function run(): void
    {
        $names = [
            'AI & Technology',
            'Analytics & Experimentation',
            'Content & Organic',
            'Creative',
            'Finance',
            'Influencer, Affiliate & PR',
            'Marketing Strategy & Brand',
            'Operations & Supply Chain',
            'Paid Media',
            'Physical Product Development & Commercialization',
            'Retail & Marketplace',
            'Retention & CRM',
        ];

        foreach ($names as $position => $name) {
            ProfessionalFunction::query()->updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'sort_order' => $position],
            );
        }
    }
}
