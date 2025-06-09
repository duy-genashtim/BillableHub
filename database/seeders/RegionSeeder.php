<?php
namespace Database\Seeders;

use App\Models\Region;
use Illuminate\Database\Seeder;

class RegionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $regions = [
            "America (Cohorts 1-5)",
            "EMEA",
            "ID/PH",
            "MY/SG",
            "America (Cohort 6)",
        ];

        foreach ($regions as $regionName) {
            Region::firstOrCreate(
                ['name' => $regionName],
                [
                    'description' => $regionName,
                    'is_active'   => true,
                ]
            );
        }
    }
}