<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ProductionReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        $referenceData = app(TmscSeeder::class);

        $referenceData->seedFeeCategories();
        $referenceData->seedSubthemes();
        $referenceData->seedInstitutions();
        $referenceData->seedConferenceSettings();
    }
}
