<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drivers', function (Blueprint $table) {
            $table->id();

            $table->string('first_name', 64);
            $table->string('last_name', 64);
            $table->string('employee_id', 32)->nullable()->unique();
            $table->string('email', 191)->nullable();
            $table->string('phone', 32)->nullable();

            $table->date('hired_on')->nullable();
            $table->date('terminated_on')->nullable();
            $table->string('status', 16)->default('active')->index();

            // Kansas CDL
            $table->string('license_number', 32)->nullable();
            $table->string('license_state', 2)->default('KS');
            $table->string('license_class', 1)->nullable();
            $table->date('license_issued_on')->nullable();
            $table->date('license_expires_on')->nullable()->index();
            $table->jsonb('endorsements')->nullable();
            $table->string('restrictions', 64)->nullable();

            // Medical + certifications
            $table->date('dot_medical_expires_on')->nullable()->index();
            $table->date('first_aid_cpr_expires_on')->nullable();
            $table->date('defensive_driving_expires_on')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['license_state', 'license_number']);
            $table->index(['last_name', 'first_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drivers');
    }
};
