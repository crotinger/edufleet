<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $resources = ['vehicle', 'driver', 'student', 'trip', 'trip_request', 'trip_reservation', 'maintenance', 'inspection', 'registration', 'route', 'fuel_log', 'ridership', 'user', 'role'];
        $abilities = ['view_any', 'view', 'create', 'update', 'delete', 'restore', 'force_delete'];

        foreach ($resources as $resource) {
            foreach ($abilities as $ability) {
                Permission::findOrCreate("{$ability}_{$resource}");
            }
        }

        // Custom permissions not tied to a resource model
        Permission::findOrCreate('view_audit_log');
        Permission::findOrCreate('approve_trip_request');
        Permission::findOrCreate('deny_trip_request');
        Permission::findOrCreate('view_vehicle_availability');
        Permission::findOrCreate('use_route_optimizer');

        $superAdmin = Role::findOrCreate('super-admin');
        $director   = Role::findOrCreate('transportation-director');
        $mechanic   = Role::findOrCreate('mechanic');
        $driver     = Role::findOrCreate('driver');
        $teacher    = Role::findOrCreate('teacher');
        $viewer     = Role::findOrCreate('viewer');

        $superAdmin->syncPermissions(Permission::all());

        $director->syncPermissions(
            Permission::whereNot(fn ($q) => $q->where('name', 'like', '%_user')->orWhere('name', 'like', '%_role'))
                ->orWhereIn('name', ['view_audit_log', 'approve_trip_request', 'deny_trip_request', 'view_vehicle_availability', 'use_route_optimizer'])
                ->get()
        );

        $mechanic->syncPermissions(
            Permission::where(fn ($q) => $q
                ->where('name', 'like', '%_vehicle')
                ->orWhere('name', 'like', '%_maintenance')
                ->orWhere('name', 'like', '%_inspection'))
                ->orWhereIn('name', ['view_any_trip', 'view_trip', 'view_vehicle_availability'])
                ->get()
        );

        $driver->syncPermissions(
            Permission::whereIn('name', [
                'view_any_vehicle', 'view_vehicle',
                'view_any_route', 'view_route',
                'view_any_student', 'view_student',
                'view_any_ridership', 'view_ridership', 'create_ridership',
                'view_any_trip', 'view_trip', 'create_trip',
                'create_fuel_log',
            ])->get()
        );

        $teacher->syncPermissions(
            Permission::whereIn('name', [
                // Teachers submit vehicle requests and see their own
                'view_any_trip_request', 'view_trip_request', 'create_trip_request', 'update_trip_request',
                // And can see what vehicles are out / available when planning
                'view_vehicle_availability',
            ])->get()
        );

        $viewer->syncPermissions(Permission::where('name', 'like', 'view_%')->get());
    }
}
