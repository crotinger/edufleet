<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inspection_templates', function (Blueprint $table) {
            // 'pre_trip' or 'post_trip'. Existing rows are pre-trip by default.
            $table->string('inspection_type', 16)->default('pre_trip')->after('name')->index();
        });

        // Make the default explicit on existing rows (was already pre_trip
        // via the default, but this catches any row inserted under raw SQL).
        DB::table('inspection_templates')->whereNull('inspection_type')->update(['inspection_type' => 'pre_trip']);
    }

    public function down(): void
    {
        Schema::table('inspection_templates', function (Blueprint $table) {
            $table->dropColumn('inspection_type');
        });
    }
};
