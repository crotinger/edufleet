<?php

namespace Database\Seeders;

use App\Models\InspectionTemplate;
use App\Models\InspectionTemplateItem;
use Illuminate\Database\Seeder;

/**
 * Idempotent seeder for default inspection templates (pre-trip + post-trip).
 *
 *   docker compose exec app php artisan db:seed --class=InspectionTemplateSeeder
 *
 * Reruns safely — items are wiped (including soft-deleted ones) and
 * recreated from the canonical list so a rerun = "reset to defaults"
 * for that template.
 */
class InspectionTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedBusPreTripTemplate();
        $this->seedBusPostTripTemplate();
        $this->seedLightVehiclePreTripTemplate();
        $this->seedLightVehiclePostTripTemplate();
    }

    private function seedBusPreTripTemplate(): void
    {
        $template = InspectionTemplate::updateOrCreate(
            ['name' => 'Bus — daily pre-trip'],
            [
                'inspection_type' => InspectionTemplate::TYPE_PRE_TRIP,
                'vehicle_type' => 'bus',
                'description' => 'Standard daily pre-trip for school buses. Must be completed and signed before trip start.',
                'active' => true,
            ],
        );

        // Matches the district's standard 20-item paper pre-check sheet,
        // in the same order drivers are used to. Critical flags applied to
        // safety-of-life items that should block trip start on failure.
        $items = [
            ['Mirrors', 'Mirrors — Inside & Out', true],
            ['Emergency equipment', 'Emergency Equipment', true],
            ['Visibility', 'Windshield Wipers & Washers', false],
            ['Brakes', 'Brakes — Foot Pedal, Hand', true],
            ['Controls', 'Gauges, Buzzers & Horn', true],
            ['Controls', 'Steering & Seatbelt', true],
            ['Visibility', 'Heater / Defroster', false],
            ['Interior', 'Interior Lights — Step & Dome', false],
            ['Emergency exits', 'Emergency Exits — Windows, Door & Roof', true],
            ['Tires', 'Fasteners — Belts', false],
            ['Tires', 'Tires & Wheels', true],
            ['Undercarriage', 'Exhaust System', false],
            ['Undercarriage', 'Springs, Hangers & Body Clamps', false],
            ['Lights', 'Headlights, Marker Lights', true],
            ['Lights', 'Stopped Lights, Tail Lights & Backup Lights', true],
            ['Lights', 'Signals — Turn, 4 Way', true],
            ['Lights', 'Flashers — Yellow, Red, Stop Arm', true],
            ['Student area', 'Child Check (Sign hung on back window of bus)', true],
            ['Student area', 'Doors & Windows Closed', false],
            ['Student area', 'Bus Clean & Fueled (Pick up trash, Sweep floors)', false],
        ];

        $this->syncItems($template, $items);
    }

    private function seedBusPostTripTemplate(): void
    {
        $template = InspectionTemplate::updateOrCreate(
            ['name' => 'Bus — daily post-trip'],
            [
                'inspection_type' => InspectionTemplate::TYPE_POST_TRIP,
                'vehicle_type' => 'bus',
                'description' => 'Standard daily post-trip for school buses. Completed before leaving the bus for the day.',
                'active' => true,
            ],
        );

        // Short post-trip — student-safety walk + anything that came up
        // while driving. Critical flags apply to items that should
        // immediately take the bus out of service until a mechanic signs off.
        $items = [
            ['Student area', 'Child Check — walked aisle, no student remaining on bus', true],
            ['Student area', 'Interior — no new damage, vandalism, or lost items reported', false],
            ['Controls', 'No new mechanical issues noticed while driving (pulls, noises, warning lights)', true],
            ['Lights', 'All lights still functional after trip (no new bulb outages)', false],
            ['Exterior', 'Exterior — no new damage, all mirrors intact', false],
            ['Fuel', 'Fuel level adequate for next trip (or refueled)', false],
            ['Cleanliness', 'Bus swept, trash removed, windows up', false],
            ['Security', 'Doors closed, bus locked and secured for the night', false],
        ];

        $this->syncItems($template, $items);
    }

    private function seedLightVehiclePreTripTemplate(): void
    {
        $template = InspectionTemplate::updateOrCreate(
            ['name' => 'Light vehicle — daily pre-trip'],
            [
                'inspection_type' => InspectionTemplate::TYPE_PRE_TRIP,
                'vehicle_type' => 'light_vehicle',
                'description' => 'Quick pre-trip for vans, Suburbans, and light pickups.',
                'active' => true,
            ],
        );

        $items = [
            ['Lights', 'Headlights (high + low) and turn signals', true],
            ['Lights', 'Brake lights', true],
            ['Tires', 'Visual tread and pressure check', true],
            ['Tires', 'No cuts, bulges, or low air', true],
            ['Fluids', 'No leaks visible under vehicle', true],
            ['Interior', 'Horn, wipers, defroster functional', false],
            ['Interior', 'All seat belts present and functional', true],
            ['Emergency equipment', 'First-aid kit and reflective triangles present', false],
        ];

        $this->syncItems($template, $items);
    }

    private function seedLightVehiclePostTripTemplate(): void
    {
        $template = InspectionTemplate::updateOrCreate(
            ['name' => 'Light vehicle — daily post-trip'],
            [
                'inspection_type' => InspectionTemplate::TYPE_POST_TRIP,
                'vehicle_type' => 'light_vehicle',
                'description' => 'Quick post-trip for vans, Suburbans, and light pickups.',
                'active' => true,
            ],
        );

        $items = [
            ['Controls', 'No new mechanical issues noticed while driving', true],
            ['Exterior', 'No new damage noticed during trip', false],
            ['Fuel', 'Fuel level adequate for next trip', false],
            ['Cleanliness', 'Interior clean, trash removed', false],
            ['Security', 'Doors closed, vehicle locked', false],
        ];

        $this->syncItems($template, $items);
    }

    /**
     * Sync items for a template. Wipes existing (including soft-deleted)
     * rows first so a re-run produces exactly the canonical set.
     *
     * @param array<int, array{0: string, 1: string, 2: bool}> $items
     */
    private function syncItems(InspectionTemplate $template, array $items): void
    {
        InspectionTemplateItem::withTrashed()
            ->where('inspection_template_id', $template->id)
            ->forceDelete();

        foreach ($items as $order => [$category, $description, $isCritical]) {
            InspectionTemplateItem::create([
                'inspection_template_id' => $template->id,
                'category' => $category,
                'description' => $description,
                'item_order' => $order,
                'is_critical' => $isCritical,
            ]);
        }
    }
}
