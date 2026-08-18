<?php

namespace Database\Seeders;

use App\Models\Specialty;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SpecialtySeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            'Cardiology',
            'Dermatology',
            'Family Medicine',
            'Internal Medicine',
            'Neurology',
            'Obstetrics and Gynecology',
            'Oncology',
            'Orthopedics',
            'Pediatrics',
            'Psychiatry',
        ] as $name) {
            Specialty::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name]);
        }
    }
}
