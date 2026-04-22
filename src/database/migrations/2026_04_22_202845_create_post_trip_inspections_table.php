<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_trip_inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained()->nullOnDelete();
            // Post-trip inspections are always attached to a specific Trip
            // (unlike pre-trip, which starts before the Trip exists). Still
            // nullable so a damaged Trip row can be cleaned up without
            // cascading the inspection away.
            $table->foreignId('trip_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('inspection_template_id')->constrained()->restrictOnDelete();

            $table->timestamp('completed_at');

            // Same overall_result + defect_status shape as pre-trip so the
            // admin triage flow is identical. Unlike pre-trip, a "failed"
            // overall_result here doesn't block anything — it just marks
            // the vehicle as needing attention before the next trip.
            $table->string('overall_result', 24)->default('passed')->index();
            $table->string('defect_status', 24)->nullable()->index();

            $table->unsignedInteger('odometer_miles')->nullable();
            $table->string('signature_name', 128)->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['vehicle_id', 'completed_at']);
            $table->index(['driver_id', 'completed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_trip_inspections');
    }
};
