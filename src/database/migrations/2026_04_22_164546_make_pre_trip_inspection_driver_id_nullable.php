<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Quicktrip drivers may not have a matching Driver row (they just
        // type a name). We still capture attribution via signature_name.
        Schema::table('pre_trip_inspections', function (Blueprint $table) {
            $table->foreignId('driver_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('pre_trip_inspections', function (Blueprint $table) {
            $table->foreignId('driver_id')->nullable(false)->change();
        });
    }
};
