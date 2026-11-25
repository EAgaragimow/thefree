<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Roles belong to a function, and brands pick roles as well as functions.
     *
     * The role first seeded as "Marketer" was a typo for "Affiliate Marketer"; it is renamed in
     * place so anyone who already picked it keeps it. Run the catalog seeders afterwards to
     * attach every role to its function.
     */
    public function up(): void
    {
        Schema::table('professional_roles', function (Blueprint $table) {
            $table->foreignId('professional_function_id')->nullable()->constrained()->nullOnDelete();
        });

        DB::table('professional_roles')
            ->where('slug', 'marketer')
            ->update(['name' => 'Affiliate Marketer', 'slug' => 'affiliate-marketer']);

        Schema::create('brand_profile_professional_role', function (Blueprint $table) {
            $table->foreignId('brand_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('professional_role_id')->constrained()->cascadeOnDelete();
            $table->primary(['brand_profile_id', 'professional_role_id'], 'brand_profile_role_primary');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brand_profile_professional_role');

        Schema::table('professional_roles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('professional_function_id');
        });

        DB::table('professional_roles')
            ->where('slug', 'affiliate-marketer')
            ->update(['name' => 'Marketer', 'slug' => 'marketer']);
    }
};
