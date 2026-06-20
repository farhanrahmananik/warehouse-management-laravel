<?php

namespace Database\Seeders;

use App\Models\Unit;
use Illuminate\Database\Seeder;

class UnitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $units = [
            [
                'name' => 'Piece',
                'short_name' => 'pcs',
                'description' => 'Single countable item unit.',
            ],
            [
                'name' => 'Box',
                'short_name' => 'box',
                'description' => 'Box-based product packaging unit.',
            ],
            [
                'name' => 'Carton',
                'short_name' => 'ctn',
                'description' => 'Carton-based product packaging unit.',
            ],
            [
                'name' => 'Kilogram',
                'short_name' => 'kg',
                'description' => 'Weight unit measured in kilograms.',
            ],
            [
                'name' => 'Gram',
                'short_name' => 'g',
                'description' => 'Weight unit measured in grams.',
            ],
            [
                'name' => 'Liter',
                'short_name' => 'l',
                'description' => 'Volume unit measured in liters.',
            ],
            [
                'name' => 'Milliliter',
                'short_name' => 'ml',
                'description' => 'Volume unit measured in milliliters.',
            ],
            [
                'name' => 'Meter',
                'short_name' => 'm',
                'description' => 'Length unit measured in meters.',
            ],
            [
                'name' => 'Pack',
                'short_name' => 'pack',
                'description' => 'Pack-based product packaging unit.',
            ],
            [
                'name' => 'Dozen',
                'short_name' => 'doz',
                'description' => 'Count unit representing twelve items.',
            ],
        ];

        foreach ($units as $unit) {
            $record = Unit::withTrashed()->updateOrCreate(
                ['short_name' => $unit['short_name']],
                [
                    ...$unit,
                    'is_active' => true,
                ]
            );

            if ($record->trashed()) {
                $record->restore();
            }
        }
    }
}
