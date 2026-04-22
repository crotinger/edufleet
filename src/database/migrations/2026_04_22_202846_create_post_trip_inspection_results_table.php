<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_trip_inspection_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_trip_inspection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inspection_template_item_id')->nullable()->constrained()->nullOnDelete();

            // Snapshot template data at result time so later template edits
            // don't mutate history.
            $table->string('category_snapshot', 64);
            $table->string('description_snapshot', 255);
            $table->boolean('was_critical')->default(false);

            $table->string('result', 16);   // pass | fail | na
            $table->text('comment')->nullable();

            $table->timestamps();

            $table->index(['post_trip_inspection_id', 'result']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_trip_inspection_results');
    }
};
