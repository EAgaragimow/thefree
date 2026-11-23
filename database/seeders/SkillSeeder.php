<?php

namespace Database\Seeders;

use App\Models\Skill;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SkillSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $skills = [
            'Laravel', 'PHP', 'JavaScript', 'React', 'Vue', 'Figma', 'Branding', 'Copywriting',
            'Google Ads', 'SEO', 'Shopify', 'Product Strategy', 'Illustration', 'Video Editing',
        ];

        foreach ($skills as $name) {
            Skill::query()->updateOrCreate(['slug' => Str::slug($name)], ['name' => $name]);
        }
    }
}
