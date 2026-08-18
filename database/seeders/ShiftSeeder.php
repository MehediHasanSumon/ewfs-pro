<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Shift;

class ShiftSeeder extends Seeder
{
    public function run(): void
    {
        $shifts = [
            [
                'name' => 'Morning Shift',
                'start_time' => '07:00:00',
                'end_time' => '15:00:00',
                'status' => true
            ],
            [
                'name' => 'Evening Shift', 
                'start_time' => '15:00:00',
                'end_time' => '23:00:00',
                'status' => true
            ],
            [
                'name' => 'Night Shift',
                'start_time' => '23:00:00', 
                'end_time' => '07:00:00',
                'status' => false
            ]
        ];

        foreach ($shifts as $shift) {
            Shift::create($shift);
        }
    }
}