<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Every freelancer profile gets a stable, unique slug for its public address. Existing profiles
     * are numbered in the order they were created, following the same rules as new ones: the first
     * name, then first and last name, then first and last name with a random six-letter suffix.
     */
    public function up(): void
    {
        Schema::table('freelancer_profiles', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('user_id');
        });

        $taken = [];

        DB::table('freelancer_profiles')
            ->join('users', 'users.id', '=', 'freelancer_profiles.user_id')
            ->orderBy('freelancer_profiles.id')
            ->get(['freelancer_profiles.id', 'users.name'])
            ->each(function (object $profile) use (&$taken): void {
                $slug = $this->slugFor((string) $profile->name, $taken);
                $taken[$slug] = true;

                DB::table('freelancer_profiles')->where('id', $profile->id)->update(['slug' => $slug]);
            });

        Schema::table('freelancer_profiles', function (Blueprint $table) {
            $table->string('slug')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('freelancer_profiles', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }

    /**
     * @param  array<string, true>  $taken
     */
    private function slugFor(string $name, array $taken): string
    {
        $words = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $first = Str::slug($words[0] ?? '');
        $last = count($words) > 1 ? Str::slug($words[array_key_last($words)]) : '';
        $base = $first !== '' ? $first : 'freelancer';

        $full = $last !== '' ? "{$base}-{$last}" : $base;

        foreach (array_unique([$base, $full]) as $candidate) {
            if (! isset($taken[$candidate])) {
                return $candidate;
            }
        }

        do {
            $slug = $full.'-'.Str::lower(Str::random(6));
        } while (isset($taken[$slug]));

        return $slug;
    }
};
