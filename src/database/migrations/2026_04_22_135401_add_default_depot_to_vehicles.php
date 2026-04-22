<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            // Where this vehicle usually sits overnight — often the driver's
            // home. Fed into the route optimizer as the vehicle's start point,
            // overridable per-run.
            $table->decimal('default_depot_lat', 10, 6)->nullable()->after('key_barcode');
            $table->decimal('default_depot_lng', 10, 6)->nullable()->after('default_depot_lat');
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn(['default_depot_lat', 'default_depot_lng']);
        });
    }
};
