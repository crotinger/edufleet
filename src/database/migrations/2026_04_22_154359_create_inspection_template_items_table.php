<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspection_template_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspection_template_id')->constrained()->cascadeOnDelete();
            $table->string('category', 64)->index();   // "Brakes", "Lights", "Emergency equipment", ...
            $table->string('description', 255);
            $table->unsignedInteger('item_order')->default(0);
            // Critical items are hard-fails — a failed critical item blocks
            // trip start from the Quicktrip flow. Non-critical fails flag the
            // inspection as "passed with defects" without blocking.
            $table->boolean('is_critical')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['inspection_template_id', 'item_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_template_items');
    }
};
