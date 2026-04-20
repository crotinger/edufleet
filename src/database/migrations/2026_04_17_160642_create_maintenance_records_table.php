<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->string('service_type', 48)->index();
            $table->date('performed_on')->index();
            $table->string('performed_by', 128)->nullable();
            $table->unsignedInteger('odometer_at_service')->nullable();
            $table->unsignedInteger('cost_cents')->nullable();

            $table->unsignedInteger('next_due_miles')->nullable()->index();
            $table->date('next_due_on')->nullable()->index();
            $table->unsignedInteger('interval_miles')->nullable();
            $table->unsignedSmallInteger('interval_months')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['vehicle_id', 'service_type', 'performed_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_records');
    }
};
