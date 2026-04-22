<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspection_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 128);
            // Optional scoping — which vehicle type this template applies to.
            // NULL means any vehicle; the Quicktrip flow picks the active
            // template for the vehicle's type, falling back to a universal
            // template.
            $table->string('vehicle_type', 32)->nullable()->index();
            $table->text('description')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_templates');
    }
};
