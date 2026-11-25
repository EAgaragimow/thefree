<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A brand now picks one entry of the industry catalog instead of typing free text. Text that
     * matches a catalog entry by name is carried over; anything else is dropped.
     */
    public function up(): void
    {
        Schema::table('brand_profiles', function (Blueprint $table) {
            $table->foreignId('industry_id')->nullable()->after('description')->constrained()->nullOnDelete();
        });

        $industries = DB::table('industries')->pluck('id', 'name')->mapWithKeys(fn ($id, $name) => [mb_strtolower($name) => $id]);

        DB::table('brand_profiles')->whereNotNull('industry')->get(['id', 'industry'])->each(function (object $brand) use ($industries): void {
            $industryId = $industries[mb_strtolower(trim($brand->industry))] ?? null;

            if ($industryId !== null) {
                DB::table('brand_profiles')->where('id', $brand->id)->update(['industry_id' => $industryId]);
            }
        });

        Schema::table('brand_profiles', function (Blueprint $table) {
            $table->dropColumn('industry');
        });
    }

    public function down(): void
    {
        Schema::table('brand_profiles', function (Blueprint $table) {
            $table->string('industry')->nullable()->after('description');
        });

        DB::table('brand_profiles')->whereNotNull('industry_id')->get(['id', 'industry_id'])->each(function (object $brand): void {
            DB::table('brand_profiles')->where('id', $brand->id)->update([
                'industry' => DB::table('industries')->where('id', $brand->industry_id)->value('name'),
            ]);
        });

        Schema::table('brand_profiles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('industry_id');
        });
    }
};
