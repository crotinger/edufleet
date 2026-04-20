<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->restrictOnDelete();
            $table->foreignId('driver_id')->constrained()->restrictOnDelete();
            $table->string('trip_type', 32)->index();
            $table->string('purpose', 191)->nullable();
            $table->timestampTz('started_at')->index();
            $table->timestampTz('ended_at')->nullable()->index();
            $table->unsignedInteger('start_odometer');
            $table->unsignedInteger('end_odometer')->nullable();
            $table->unsignedSmallInteger('passengers')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['vehicle_id', 'started_at']);
            $table->index(['driver_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trips');
    }
};
