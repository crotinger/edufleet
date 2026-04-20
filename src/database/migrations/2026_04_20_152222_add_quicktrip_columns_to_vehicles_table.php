<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('quicktrip_pin', 16)->nullable()->after('notes');
            $table->string('key_barcode', 64)->nullable()->unique()->after('quicktrip_pin');
        });

        DB::table('vehicles')->whereNull('quicktrip_pin')->get(['id'])->each(function ($v) {
            DB::table('vehicles')
                ->where('id', $v->id)
                ->update(['quicktrip_pin' => str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT)]);
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropUnique(['key_barcode']);
            $table->dropColumn(['quicktrip_pin', 'key_barcode']);
        });
    }
};
