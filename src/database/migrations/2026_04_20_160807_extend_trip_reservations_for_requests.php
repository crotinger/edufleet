<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_reservations', function (Blueprint $table) {
            // Teacher requests start without a vehicle assigned
            $table->timestampTz('desired_start_at')->nullable()->after('expected_return_at');
            $table->string('preferred_vehicle_type', 32)->nullable()->after('desired_start_at');
            $table->foreignId('requested_by_user_id')->nullable()->after('issued_by_user_id')->constrained('users')->nullOnDelete();

            // Denial tracking (separate from cancellation — denial is admin rejecting a request)
            $table->string('denied_reason', 255)->nullable()->after('notes');
            $table->foreignId('denied_by_user_id')->nullable()->after('denied_reason')->constrained('users')->nullOnDelete();
            $table->timestampTz('denied_at')->nullable()->after('denied_by_user_id');
        });

        // Relax NOT NULL on vehicle_id — teacher requests come in without an assigned vehicle.
        DB::statement('ALTER TABLE trip_reservations ALTER COLUMN vehicle_id DROP NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE trip_reservations ALTER COLUMN vehicle_id SET NOT NULL');

        Schema::table('trip_reservations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('requested_by_user_id');
            $table->dropConstrainedForeignId('denied_by_user_id');
            $table->dropColumn(['desired_start_at', 'preferred_vehicle_type', 'denied_reason', 'denied_at']);
        });
    }
};
