<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('type', 32)->index();
            $table->string('unit_number', 32);
            $table->string('vin', 32)->nullable()->unique();
            $table->string('license_plate', 16)->nullable();
            $table->string('make', 64)->nullable();
            $table->string('model', 64)->nullable();
            $table->smallInteger('year')->nullable();
            $table->string('fuel_type', 16)->nullable();
            $table->unsignedInteger('odometer_miles')->default(0);
            $table->smallInteger('capacity_passengers')->nullable();
            $table->string('status', 16)->default('active')->index();
            $table->date('acquired_on')->nullable();
            $table->date('retired_on')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['type', 'unit_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
