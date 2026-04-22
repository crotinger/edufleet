<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pre_trip_inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained()->restrictOnDelete();
            // Null while the driver is filling it out in the Quicktrip flow
            // before a Trip exists; linked to the Trip once it's created.
            $table->foreignId('trip_id')->nullable()->constrained()->nullOnDelete();
            // Which template was used. Restrict-on-delete so we never orphan
            // an inspection from the template it was built against.
            $table->foreignId('inspection_template_id')->constrained()->restrictOnDelete();

            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();

            // overall_result is derived on completion from the per-item
            // results; denormalized here for fast filtering.
            //   passed              — all items pass / N-A
            //   passed_with_defects — non-critical items failed
            //   failed              — at least one critical item failed
            //   in_progress         — not yet completed
            $table->string('overall_result', 24)->default('in_progress')->index();

            // Maintenance follow-up:
            //   open       — defects exist, awaiting admin triage
            //   acknowledged — admin saw it but no maintenance needed
            //   dispatched — a maintenance record was created from this report
            //   closed     — not applicable (no defects) or manually closed
            $table->string('defect_status', 24)->nullable()->index();

            $table->unsignedInteger('odometer_miles')->nullable();
            $table->string('signature_name', 128)->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['vehicle_id', 'started_at']);
            $table->index(['driver_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pre_trip_inspections');
    }
};
