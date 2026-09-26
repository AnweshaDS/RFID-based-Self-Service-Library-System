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
            [
                'uid' => '04:B2:11:8A:92:31',
                'cardnumber' => 'STU001',
                'active' => true,
            ],
            [
                'uid' => '04:A3:91:7B:22:18',
                'cardnumber' => 'STU002',
                'active' => true,
            ],
        ];

        foreach ($mappings as $mapping) {
            RfidCard::updateOrCreate(
                ['uid' => $mapping['uid']],
                [
                    'cardnumber' => $mapping['cardnumber'],
                    'active' => $mapping['active'],
                ]
            );
        }
    }
}
