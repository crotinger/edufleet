<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->string('key_barcode', 64)->nullable()->index();
            $table->string('source', 16)->index();
            $table->string('purpose', 191);
            $table->string('planned_trip_type', 32);
            $table->string('expected_driver_name', 128)->nullable();
            $table->unsignedSmallInteger('expected_passengers')->nullable();
            $table->timestampTz('expected_return_at')->nullable();
            $table->timestampTz('issued_at');
            $table->foreignId('issued_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 16)->default('reserved')->index();
            $table->foreignId('trip_id')->nullable()->constrained('trips')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['vehicle_id', 'status']);
        });

        Schema::table('trips', function (Blueprint $table) {
            $table->foreign('reservation_id')->references('id')->on('trip_reservations')->nullOnDelete();
            $table->index(['reservation_id']);
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropForeign(['reservation_id']);
            $table->dropIndex(['reservation_id']);
        });

        Schema::dropIfExists('trip_reservations');
    }
};
