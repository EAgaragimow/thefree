<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `is_newbie` is a technical flag: true for a month after the profile is created, which gives the
     * freelancer a head start in the directory. `app:expire-newbie-profiles` switches it off.
     */
    public function up(): void
    {
        Schema::table('freelancer_profiles', function (Blueprint $table) {
            $table->boolean('is_newbie')->default(false)->after('score')->index();
        });

        DB::table('freelancer_profiles')
            ->where('created_at', '>', now()->subMonth())
            ->update(['is_newbie' => true]);
    }

    public function down(): void
    {
        Schema::table('freelancer_profiles', function (Blueprint $table) {
            $table->dropIndex(['is_newbie']);
            $table->dropColumn('is_newbie');
        });
    }
};
