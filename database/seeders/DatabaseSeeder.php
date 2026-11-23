<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            CategorySeeder::class,
            SkillSeeder::class,
        ]);

        if (app()->environment('local')) {
            User::factory()->admin()->create([
                'name' => 'Admin',
                'email' => 'admin@thefree.test',
            ]);

            User::factory()->client()->create([
                'name' => 'Test Client',
                'email' => 'client@thefree.test',
            ]);
        }
    }
}
