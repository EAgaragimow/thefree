<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `published_at` now means "first published"; `content_updated_at` is when an approved
     * revision last changed the live content (unlike `updated_at`, it ignores score/featured edits).
     */
    public function up(): void
    {
        Schema::table('freelancer_profiles', function (Blueprint $table) {
            $table->timestamp('content_updated_at')->nullable()->after('published_at');
        });

        DB::table('freelancer_profiles')
            ->whereNotNull('published_at')
            ->update(['content_updated_at' => DB::raw('published_at')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('freelancer_profiles', function (Blueprint $table) {
            $table->dropColumn('content_updated_at');
        });
    }
};
