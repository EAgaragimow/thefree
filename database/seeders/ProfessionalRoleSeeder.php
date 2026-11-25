<?php

namespace Database\Seeders;

use App\Models\ProfessionalFunction;
use App\Models\ProfessionalRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProfessionalRoleSeeder extends Seeder
{
    /**
     * Seed the roles a freelancer can hold, each under its function. Safe to run again: entries
     * are matched by slug, and functions are created if they are missing.
     */
    public function run(): void
    {
        $rolesByFunction = [
            'Influencer, Affiliate & PR' => ['Affiliate Marketer', 'Influencer Marketer', 'Partnerships Marketer', 'PR Director'],
            'AI & Technology' => ['AI Automation Expert', 'E-Commerce Manager', 'Head of Technology', 'Product Manager', 'Shopify Developer'],
            'Retail & Marketplace' => ['Amazon Marketer', 'Head of Retail'],
            'Analytics & Experimentation' => ['Analytics Director', 'CRO Expert', 'Data Analyst'],
            'Marketing Strategy & Brand' => ['Brand Marketer', 'Chief Executive Officer', 'Chief Marketing Officer', 'Head of Growth'],
            'Operations & Supply Chain' => ['Chief Operating Officer', 'Demand Planner', 'Fulfillment & Logistics Manager'],
            'Content & Organic' => ['Content Creator', 'Content Marketer', 'SEO Marketer', 'Social Media Manager'],
            'Creative' => ['Copywriter', 'Creative Director', 'Graphic Designer', 'Producer', 'UI/UX Designer', 'Video Editor'],
            'Retention & CRM' => ['CRM Marketer', 'CX Director', 'Head of Retention'],
            'Paid Media' => ['Growth Marketer', 'Paid Search Marketer', 'Paid Social Marketer', 'Performance Creative Lead', 'Programmatic Marketer'],
            'Physical Product Development & Commercialization' => ['Head of Product', 'Product Launch Manager'],
            'Finance' => ['Strategic Finance Lead'],
        ];

        $this->call(ProfessionalFunctionSeeder::class);

        foreach ($rolesByFunction as $functionName => $roleNames) {
            $function = ProfessionalFunction::query()->where('slug', Str::slug($functionName))->firstOrFail();

            foreach ($roleNames as $position => $name) {
                ProfessionalRole::query()->updateOrCreate(
                    ['slug' => Str::slug($name)],
                    ['name' => $name, 'professional_function_id' => $function->id, 'sort_order' => $position],
                );
            }
        }
    }
}
