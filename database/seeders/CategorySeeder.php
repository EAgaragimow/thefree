<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tree = [
            'Design' => ['Brand Identity', 'UI/UX Design', 'Illustration'],
            'Development' => ['Web Development', 'Mobile Development', 'E-commerce'],
            'Marketing' => ['SEO', 'Social Media', 'Content Strategy'],
            'Consulting' => ['Business Strategy', 'Product Management'],
        ];

        $order = 0;

        foreach ($tree as $parentName => $children) {
            $parent = Category::query()->updateOrCreate(
                ['slug' => Str::slug($parentName)],
                ['name' => $parentName, 'parent_id' => null, 'sort_order' => $order++],
            );

            foreach ($children as $childOrder => $childName) {
                Category::query()->updateOrCreate(
                    ['slug' => Str::slug($childName)],
                    ['name' => $childName, 'parent_id' => $parent->id, 'sort_order' => $childOrder],
                );
            }
        }
    }
}
