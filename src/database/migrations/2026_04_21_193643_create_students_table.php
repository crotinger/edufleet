<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();

            $table->string('first_name', 64);
            $table->string('last_name', 64);
            $table->string('grade', 8)->nullable();
            $table->string('student_id', 32)->nullable()->unique();
            $table->string('attendance_center', 64)->nullable()->index();

            $table->text('home_address')->nullable();
            $table->decimal('home_lat', 10, 6)->nullable();
            $table->decimal('home_lng', 10, 6)->nullable();
            $table->timestamp('geocoded_at')->nullable();

            $table->decimal('distance_to_school_miles', 5, 2)->nullable();
            $table->boolean('hazardous_route')->default(false);

            $table->text('medical_notes')->nullable();
            $table->string('emergency_contact_name', 128)->nullable();
            $table->string('emergency_contact_phone', 32)->nullable();

            $table->string('photo_path', 255)->nullable();
            $table->boolean('active')->default(true)->index();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['last_name', 'first_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
