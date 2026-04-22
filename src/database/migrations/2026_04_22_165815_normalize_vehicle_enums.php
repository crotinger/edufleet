<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The Vehicle form used TextInputs for `type`, `status`, and
     * `fuel_type` until now, so operators could save values like "Bus" or
     * "Diesel" with mixed casing that didn't match the canonical lowercase
     * enum values (bus / light_vehicle / active / in_shop / retired /
     * diesel / gasoline / propane / electric / hybrid). That broke
     * InspectionTemplate::forVehicle() lookups and would bite any other
     * enum-aware feature. Mutator + Select fields now prevent it going
     * forward — this one-shot normalizes existing data.
     */
    public function up(): void
    {
        DB::table('vehicles')->update([
            'type' => DB::raw('lower(trim(type))'),
            'status' => DB::raw('lower(trim(status))'),
            'fuel_type' => DB::raw('CASE WHEN fuel_type IS NULL THEN NULL ELSE lower(trim(fuel_type)) END'),
        ]);
    }

    public function down(): void
    {
        // Not reversible — no canonical mapping back to prior casing.
    }
};
