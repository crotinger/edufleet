<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32)->index();
            $table->date('inspected_on')->index();
            $table->date('expires_on')->nullable()->index();
            $table->string('result', 24)->index();
            $table->string('inspector_name', 128)->nullable();
            $table->string('certificate_number', 64)->nullable();
            $table->unsignedInteger('odometer_miles')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspections');
    }
};
