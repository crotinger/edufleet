<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->string('state', 2)->default('KS');
            $table->string('plate_number', 16)->nullable();
            $table->string('registration_number', 64)->nullable();
            $table->date('registered_on')->nullable();
            $table->date('expires_on')->index();
            $table->unsignedInteger('fee_cents')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['state', 'registration_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registrations');
    }
};
