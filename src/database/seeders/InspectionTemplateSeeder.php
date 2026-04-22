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

        $items = [
            // Walkaround / exterior
            ['Walkaround', 'Body — no new damage, panels secure', false],
            ['Walkaround', 'No fluid leaks visible under bus', true],
            ['Walkaround', 'Reflectors / license plate in place and visible', false],
            // Lights
            ['Lights', 'Headlights — high and low beam', true],
            ['Lights', 'Turn signals — front, rear, side (amber + red)', true],
            ['Lights', 'Brake lights functional', true],
            ['Lights', '8-way flashers (amber + red) working', true],
            ['Lights', 'Marker and clearance lights', false],
            ['Lights', 'Stop arm deploys fully and lights activate', true],
            ['Lights', 'Crossing gate deploys (if equipped)', false],
            // Tires
            ['Tires', 'Tread depth adequate on all tires', true],
            ['Tires', 'No cuts, bulges, or foreign objects', true],
            ['Tires', 'Lug nuts tight; no missing wheel fasteners', true],
            ['Tires', 'Tire pressure within spec', false],
            // Mirrors
            ['Mirrors', 'All mirrors clean, secure, properly adjusted', false],
            // Interior / controls
            ['Interior', 'Horn works', true],
            ['Interior', 'Windshield wipers and washer fluid operational', false],
            ['Interior', 'Defroster / heater working', false],
            ['Interior', 'All gauges functional (fuel, temp, oil, air)', false],
            ['Interior', 'Driver seat belt in good condition', true],
            // Emergency equipment
            ['Emergency equipment', 'Fire extinguisher charged and secured', true],
            ['Emergency equipment', 'First-aid kit and body-fluid cleanup kit present', true],
            ['Emergency equipment', 'Reflective triangles / flares on board', false],
            ['Emergency equipment', 'All emergency exits open freely from inside', true],
            // Brakes
            ['Brakes', 'Parking brake holds', true],
            ['Brakes', 'Service brake firm and responsive', true],
            ['Brakes', 'Air pressure builds and holds (air brake buses only)', true],
            // Student area
            ['Student area', 'Aisles clear, no items left from prior trip', false],
            ['Student area', 'Seats and flooring secure, no torn upholstery with sharp edges', false],
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

    /** @param array<int, array{0: string, 1: string, 2: bool}> $items */
    private function syncItems(InspectionTemplate $template, array $items): void
    {
        foreach ($items as $order => [$category, $description, $isCritical]) {
            InspectionTemplateItem::updateOrCreate(
                [
                    'inspection_template_id' => $template->id,
                    'description' => $description,
                ],
                [
                    'category' => $category,
                    'item_order' => $order,
                    'is_critical' => $isCritical,
                ],
            );
        }
    }
}
