<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 128);
            $table->text('description')->nullable();
            $table->foreignId('default_vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->foreignId('default_driver_id')->nullable()->constrained('drivers')->nullOnDelete();
            $table->jsonb('days_of_week')->nullable();    // ['mon','tue',...]
            $table->time('departure_time')->nullable();
            $table->time('return_time')->nullable();
            $table->string('starting_location', 191)->nullable();
            $table->unsignedSmallInteger('estimated_miles')->nullable();
            $table->string('status', 16)->default('active')->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routes');
    }
};
