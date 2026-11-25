<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Users are soft-deleted, so deleting one used to leave its brand or freelancer profile behind.
     * Remove the profiles of accounts that are already deleted (their highlights, services,
     * revisions and so on cascade from the profile).
     */
    public function up(): void
    {
        foreach (['brand_profiles', 'freelancer_profiles'] as $table) {
            DB::table($table)
                ->whereIn('user_id', DB::table('users')->whereNotNull('deleted_at')->select('id'))
                ->delete();
        }
    }

    /**
     * The removed profiles cannot be brought back.
     */
    public function down(): void {}
};
