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
        Schema::create('freelancer_profile_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('freelancer_profile_id')->constrained()->cascadeOnDelete();
            $table->string('status')->index();
            $table->json('payload');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->text('last_change_request')->nullable();
            // Equals freelancer_profile_id while the revision is open and NULL once it is closed,
            // so a unique index enforces "at most one open revision per profile" on every database.
            $table->unsignedBigInteger('open_for_profile_id')->nullable()->unique();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('freelancer_profile_revisions');
    }
};
