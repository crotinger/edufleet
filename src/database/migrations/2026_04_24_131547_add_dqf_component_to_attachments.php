<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attachments', function (Blueprint $table) {
            // Which part of the FMCSA Driver Qualification File this
            // attachment satisfies (49 CFR §391). Only populated on
            // attachments owned by a Driver.
            $table->string('dqf_component', 32)->nullable()->after('category')->index();
        });
    }

    public function down(): void
    {
        Schema::table('attachments', function (Blueprint $table) {
            $table->dropColumn('dqf_component');
        });
    }
};
