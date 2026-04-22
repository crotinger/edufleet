<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pre_trip_inspection_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pre_trip_inspection_id')->constrained()->cascadeOnDelete();
            // Null-on-delete so a retired template item doesn't vaporize
            // historical records — we snapshot the description below too.
            $table->foreignId('inspection_template_item_id')->nullable()->constrained()->nullOnDelete();

            // Snapshots of the template item at the time of inspection so
            // later edits to the template don't mutate historical reports.
            $table->string('category_snapshot', 64);
            $table->string('description_snapshot', 255);
            $table->boolean('was_critical')->default(false);

            $table->string('result', 16);   // pass | fail | na
            $table->text('comment')->nullable();

            $table->timestamps();

            $table->index(['pre_trip_inspection_id', 'result']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pre_trip_inspection_results');
    }
};
