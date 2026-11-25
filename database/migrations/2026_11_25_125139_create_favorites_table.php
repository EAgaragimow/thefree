<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Freelancers a client has saved. Rows survive a client's lapse to the Free plan; they go away
     * with the client's or the freelancer's profile.
     */
    public function up(): void
    {
        Schema::create('favorites', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('freelancer_profile_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['user_id', 'freelancer_profile_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('favorites');
    }
};
