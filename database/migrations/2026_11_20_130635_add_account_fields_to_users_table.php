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
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('client')->index()->after('password');
            $table->string('status')->default('active')->index()->after('role');
            $table->string('timezone')->default('UTC')->after('status');
            $table->string('avatar_path')->nullable()->after('timezone');
            $table->json('notification_preferences')->nullable()->after('avatar_path');
            $table->string('subscription_type')->default('free-plan')->after('notification_preferences');
            $table->string('billing_state')->default('ok')->after('subscription_type');
            $table->timestamp('deactivated_at')->nullable();
            $table->timestamp('deletion_requested_at')->nullable();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropIndex(['status']);
            $table->dropSoftDeletes();
            $table->dropColumn([
                'role',
                'status',
                'timezone',
                'avatar_path',
                'notification_preferences',
                'subscription_type',
                'billing_state',
                'deactivated_at',
                'deletion_requested_at',
            ]);
        });
    }
};
