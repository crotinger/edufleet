<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->foreignId('route_id')->nullable()->after('driver_id')->constrained('routes')->nullOnDelete();
            $table->index(['route_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropIndex(['route_id', 'started_at']);
            $table->dropConstrainedForeignId('route_id');
        });
    }
};
