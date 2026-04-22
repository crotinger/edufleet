<?php

namespace Database\Seeders;

use App\Models\InspectionTemplate;
use App\Models\InspectionTemplateItem;
use Illuminate\Database\Seeder;

/**
 * Idempotent seeder for two default pre-trip inspection templates — one
 * for school buses (per standard Kansas school-bus pre-trip categories)
 * and one for light vehicles (vans, Suburbans, pickups).
 *
 * Run repeatedly with:
 *   docker compose exec app php artisan db:seed --class=InspectionTemplateSeeder
 * Existing templates at the same (name) key are updated, items re-synced
 * so operators can rerun after a content change without duplicating rows.
 */
class InspectionTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedBusTemplate();
        $this->seedLightVehicleTemplate();
    }

    private function seedBusTemplate(): void
    {
        $template = InspectionTemplate::updateOrCreate(
            ['name' => 'Bus — daily pre-trip'],
            [
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

    private function seedLightVehicleTemplate(): void
    {
        $template = InspectionTemplate::updateOrCreate(
            ['name' => 'Light vehicle — daily pre-trip'],
            [
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

    /**
     * Sync items for a template. Because admins can edit items in the UI
     * (including soft-deletions that we don't want to "resurrect" via a
     * dedup-on-description rerun), we purge everything on the template
     * first and recreate from the canonical list. This keeps the seeded
     * template authoritative — run it when you want to reset to the
     * shipped defaults.
     *
     * @param array<int, array{0: string, 1: string, 2: bool}> $items
     */
    private function syncItems(InspectionTemplate $template, array $items): void
    {
        // Wipe any existing items (including previously soft-deleted rows)
        // so a re-run produces exactly the canonical set, in canonical order.
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
