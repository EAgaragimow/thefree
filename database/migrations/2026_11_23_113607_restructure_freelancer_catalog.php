<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Replace the category tree with a flat list of functions, add the role, tool and industry
     * catalogs, and add the new freelancer profile fields.
     *
     * The old catalog is discarded on purpose: it is replaced by a new list, so existing
     * category and skill selections no longer mean anything. They are removed from live profiles,
     * brand interests and from the content of profile revisions. Run the catalog seeders afterwards.
     */
    public function up(): void
    {
        Schema::dropIfExists('category_freelancer_profile');
        Schema::dropIfExists('brand_profile_category');
        Schema::dropIfExists('categories');

        DB::table('freelancer_profile_skill')->delete();
        DB::table('brand_profile_skill')->delete();
        DB::table('skills')->delete();

        Schema::create('professional_functions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('professional_roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('tools', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('industries', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('brand_profile_professional_function', function (Blueprint $table) {
            $table->foreignId('brand_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('professional_function_id')->constrained()->cascadeOnDelete();
            $table->primary(['brand_profile_id', 'professional_function_id'], 'brand_profile_function_primary');
        });

        Schema::create('freelancer_profile_tool', function (Blueprint $table) {
            $table->foreignId('freelancer_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tool_id')->constrained()->cascadeOnDelete();
            $table->primary(['freelancer_profile_id', 'tool_id']);
        });

        Schema::create('freelancer_profile_industry', function (Blueprint $table) {
            $table->foreignId('freelancer_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('industry_id')->constrained()->cascadeOnDelete();
            $table->primary(['freelancer_profile_id', 'industry_id']);
        });

        Schema::table('freelancer_profiles', function (Blueprint $table) {
            $table->foreignId('professional_function_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('main_role_id')->nullable()->constrained('professional_roles')->nullOnDelete();
            $table->foreignId('secondary_role_id')->nullable()->constrained('professional_roles')->nullOnDelete();
            $table->foreignId('third_role_id')->nullable()->constrained('professional_roles')->nullOnDelete();
            $table->unsignedInteger('hourly_rate_cents')->nullable();
            $table->boolean('offers_monthly_retainers')->nullable();
            $table->text('monthly_retainers_description')->nullable();
            $table->unsignedInteger('monthly_retainers_from_cents')->nullable();
            $table->string('weekly_hours')->nullable();
            $table->boolean('open_to_full_time')->nullable();
            $table->json('best_fit')->nullable();
        });

        DB::table('freelancer_profile_revisions')->orderBy('id')->each(function (object $revision): void {
            $payload = json_decode((string) $revision->payload, true) ?: [];

            unset($payload['category_ids']);
            $payload['skill_ids'] = [];

            DB::table('freelancer_profile_revisions')
                ->where('id', $revision->id)
                ->update(['payload' => json_encode($payload)]);
        });
    }

    /**
     * The discarded categories and skills cannot be restored; only the structure is.
     */
    public function down(): void
    {
        Schema::table('freelancer_profiles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('professional_function_id');
            $table->dropConstrainedForeignId('main_role_id');
            $table->dropConstrainedForeignId('secondary_role_id');
            $table->dropConstrainedForeignId('third_role_id');
            $table->dropColumn([
                'hourly_rate_cents',
                'offers_monthly_retainers',
                'monthly_retainers_description',
                'monthly_retainers_from_cents',
                'weekly_hours',
                'open_to_full_time',
                'best_fit',
            ]);
        });

        Schema::dropIfExists('freelancer_profile_industry');
        Schema::dropIfExists('freelancer_profile_tool');
        Schema::dropIfExists('brand_profile_professional_function');
        Schema::dropIfExists('industries');
        Schema::dropIfExists('tools');
        Schema::dropIfExists('professional_roles');
        Schema::dropIfExists('professional_functions');

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('category_freelancer_profile', function (Blueprint $table) {
            $table->foreignId('freelancer_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->primary(['freelancer_profile_id', 'category_id']);
        });

        Schema::create('brand_profile_category', function (Blueprint $table) {
            $table->foreignId('brand_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->primary(['brand_profile_id', 'category_id']);
        });
    }
};
