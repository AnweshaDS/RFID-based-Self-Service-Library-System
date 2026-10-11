<?php

namespace Database\Seeders;

use App\Models\RfidCard;
use Illuminate\Database\Seeder;

class RfidCardSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
{
    $mappings = [
        ['uid' => '53CC54AE420001', 'cardnumber' => 'STU001', 'active' => true],
        ['uid' => '53C754AE420001', 'cardnumber' => 'STU002', 'active' => true],
        ['uid' => '53C654AE420001', 'cardnumber' => 'STU003', 'active' => true],
        ['uid' => '53C554AE420001', 'cardnumber' => 'T001',   'active' => true],
    ];

    foreach ($mappings as $mapping) {
        RfidCard::updateOrCreate(
            ['cardnumber' => $mapping['cardnumber']],
            [
                'uid' => $mapping['uid'],
                'active' => $mapping['active'],
            ]
        );
    }
}    
        
}
