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
        Schema::create('freelancer_applications', function (Blueprint $table) {
            $table->id();
            $table->string('email')->index();
            $table->string('full_name');
            $table->string('phone')->nullable();
            $table->string('location')->nullable();
            $table->string('specialty');
            $table->unsignedTinyInteger('years_experience')->nullable();
            $table->text('experience_summary');
            $table->text('about')->nullable();
            $table->json('skills')->nullable();
            $table->json('portfolio_links')->nullable();
            $table->json('social_links')->nullable();
            $table->string('status')->default('pending')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->string('invitation_token_hash', 64)->nullable()->unique();
            $table->timestamp('invitation_expires_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('freelancer_applications');
    }
};
