<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_reservations', function (Blueprint $table) {
            // When one teacher request is approved across multiple vehicles, each
            // resulting reservation gets the same split_group_id (UUID). NULL for
            // the normal one-vehicle path.
            $table->uuid('split_group_id')->nullable()->after('trip_id')->index();

            // The original teacher request that spawned this reservation (when split).
            // NULL for non-split reservations or for the primary leg of a split
            // (which re-uses the original request row).
            $table->foreignId('split_parent_request_id')
                ->nullable()
                ->after('split_group_id')
                ->constrained('trip_reservations')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('trip_reservations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('split_parent_request_id');
            $table->dropIndex(['split_group_id']);
            $table->dropColumn('split_group_id');
        });
    }
};
