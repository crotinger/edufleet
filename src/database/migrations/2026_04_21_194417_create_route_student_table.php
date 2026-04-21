<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('route_student', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();

            // Which leg(s) this student rides. Values: am, pm, both.
            $table->string('direction', 8)->default('both');

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['route_id', 'student_id'], 'route_student_unique');
            $table->index(['student_id', 'direction']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('route_student');
    }
};
