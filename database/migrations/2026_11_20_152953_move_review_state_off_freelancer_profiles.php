<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `freelancer_profiles` now only holds live (published) data. The review state moves to
     * `freelancer_profile_revisions`.
     */
    public function up(): void
    {
        Schema::table('freelancer_profiles', function (Blueprint $table) {
            $table->dropIndex(['status']);
        });

        Schema::table('freelancer_profiles', function (Blueprint $table) {
            $table->dropColumn(['status', 'published_snapshot', 'submitted_at', 'last_change_request']);
        });

        Schema::table('freelancer_profiles', function (Blueprint $table) {
            $table->bigInteger('score')->default(0)->index();
            $table->timestamp('featured_at')->nullable();
            $table->unsignedInteger('featured_sort')->nullable();
            $table->index('published_at');
            $table->index('timezone');
        });

        Schema::table('profile_reviews', function (Blueprint $table) {
            $table->foreignId('freelancer_profile_revision_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('profile_reviews', function (Blueprint $table) {
            $table->dropConstrainedForeignId('freelancer_profile_revision_id');
        });

        Schema::table('freelancer_profiles', function (Blueprint $table) {
            $table->dropIndex(['score']);
            $table->dropIndex(['published_at']);
            $table->dropIndex(['timezone']);
        });

        Schema::table('freelancer_profiles', function (Blueprint $table) {
            $table->dropColumn(['score', 'featured_at', 'featured_sort']);
            $table->string('status')->default('draft')->index();
            $table->json('published_snapshot')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->text('last_change_request')->nullable();
        });
    }
};
