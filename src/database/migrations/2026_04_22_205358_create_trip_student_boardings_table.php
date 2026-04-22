<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_student_boardings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            // Preserve the boarding record even if the Student is force-deleted
            // (we want historical accuracy for KSDE rollups).
            $table->foreignId('student_id')->constrained()->restrictOnDelete();

            $table->boolean('boarded')->default(false);
            $table->timestamp('boarded_at')->nullable();
            $table->string('stop_name', 128)->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(['trip_id', 'student_id'], 'trip_student_boardings_unique');
            $table->index('boarded');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_student_boardings');
    }
};
