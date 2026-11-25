<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Applications now carry the roles the applicant picked (in pick order, by name, like skills)
     * and the location as country, state/province and city. `location` stays as the readable
     * "City, State, Country" line, and `specialty` as the name of the main role, so existing
     * applications and everything that reads those two columns keep working.
     */
    public function up(): void
    {
        Schema::table('freelancer_applications', function (Blueprint $table) {
            $table->json('roles')->nullable()->after('specialty');
            $table->string('country')->nullable()->after('location');
            $table->string('state')->nullable()->after('country');
            $table->string('city')->nullable()->after('state');
        });
    }

    public function down(): void
    {
        Schema::table('freelancer_applications', function (Blueprint $table) {
            $table->dropColumn(['roles', 'country', 'state', 'city']);
        });
    }
};
