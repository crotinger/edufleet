<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->string('driver_name_override', 128)->nullable()->after('driver_id');
            $table->string('status', 16)->default('approved')->after('notes')->index();
            $table->timestampTz('approved_at')->nullable()->after('status');
            $table->foreignId('approved_by_user_id')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            $table->string('rejection_reason', 255)->nullable()->after('approved_by_user_id');
            $table->unsignedBigInteger('reservation_id')->nullable()->after('rejection_reason');
        });

        DB::statement('ALTER TABLE trips ALTER COLUMN driver_id DROP NOT NULL');
        DB::statement("UPDATE trips SET status = 'approved', approved_at = COALESCE(updated_at, created_at) WHERE approved_at IS NULL");
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by_user_id');
            $table->dropColumn(['driver_name_override', 'status', 'approved_at', 'rejection_reason', 'reservation_id']);
        });

        DB::statement('ALTER TABLE trips ALTER COLUMN driver_id SET NOT NULL');
    }
};
