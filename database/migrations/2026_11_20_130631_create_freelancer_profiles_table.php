<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('freelancer_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('freelancer_application_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('draft')->index();
            $table->string('photo_path')->nullable();
            $table->string('professional_title')->nullable();
            $table->text('about')->nullable();
            $table->string('location')->nullable();
            $table->string('timezone')->default('UTC');
            $table->string('website')->nullable();
            $table->json('social_links')->nullable();
            $table->json('published_snapshot')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->text('last_change_request')->nullable();
            $table->timestamps();
        });

        Schema::create('category_freelancer_profile', function (Blueprint $table) {
            $table->foreignId('freelancer_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->primary(['freelancer_profile_id', 'category_id']);
        });

        Schema::create('freelancer_profile_skill', function (Blueprint $table) {
            $table->foreignId('freelancer_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('skill_id')->constrained()->cascadeOnDelete();
            $table->primary(['freelancer_profile_id', 'skill_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('freelancer_profile_skill');
        Schema::dropIfExists('category_freelancer_profile');
        Schema::dropIfExists('freelancer_profiles');
    }
};
