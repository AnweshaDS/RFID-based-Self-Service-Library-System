<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $departments = [
            ['name' => 'Computer Science and Engineering', 'code' => 'CSE'],
            ['name' => 'Electrical and Electronic Engineering', 'code' => 'EEE'],
            ['name' => 'Mechanical Engineering', 'code' => 'ME'],
        ];

        foreach ($departments as $dept) {
            Department::updateOrCreate(
                ['code' => $dept['code']],
                ['name' => $dept['name']]
            );
        }
    }
}
