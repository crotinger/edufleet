<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->timestamp('last_geocode_attempted_at')->nullable()->after('geocoded_at');
            $table->text('last_geocode_error')->nullable()->after('last_geocode_attempted_at');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['last_geocode_attempted_at', 'last_geocode_error']);
        });
    }
};
