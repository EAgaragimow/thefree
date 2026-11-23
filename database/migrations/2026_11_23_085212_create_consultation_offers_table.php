<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A profile has at most one row per consultation type (free, paid). Rows are never deleted:
     * a type the freelancer stops offering is switched off, so bookings can keep pointing at it.
     */
    public function up(): void
    {
        Schema::create('consultation_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('freelancer_profile_id')->constrained()->cascadeOnDelete();
            $table->string('type', 16);
            $table->boolean('is_enabled')->default(false);
            $table->unsignedSmallInteger('duration_minutes');
            // USD, in cents. Always 0 for the free type.
            $table->unsignedInteger('price_cents')->default(0);
            $table->text('description')->nullable();
            // Weekly windows in the profile's time zone: [{day: "mon", start: "09:00", end: "17:00"}].
            $table->json('availability');
            $table->timestamps();

            $table->unique(['freelancer_profile_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consultation_offers');
    }
};
