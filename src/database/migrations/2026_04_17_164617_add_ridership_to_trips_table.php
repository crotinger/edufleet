<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->unsignedSmallInteger('riders_eligible')->nullable()->after('passengers');
            $table->unsignedSmallInteger('riders_ineligible')->nullable()->after('riders_eligible');
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn(['riders_eligible', 'riders_ineligible']);
        });
    }
};
