<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('route_paths', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_id')->constrained()->cascadeOnDelete();

            $table->string('version_name', 128);

            // Ordered array of stops:
            //   [{ id, name, lat, lng, order, student_id?, dwell_seconds? }, ...]
            $table->jsonb('stops')->nullable();

            // GeoJSON LineString returned by OSRM's /route/ or /trip/ endpoint.
            $table->jsonb('geometry')->nullable();

            $table->unsignedInteger('distance_meters')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();

            // Routing profile used for the last calculation (driving, bus, etc.)
            $table->string('profile', 24)->default('driving');

            // Only one active path per route — enforced by the RoutePath model's
            // saving() hook.
            $table->boolean('is_active')->default(false);

            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['route_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('route_paths');
    }
};
