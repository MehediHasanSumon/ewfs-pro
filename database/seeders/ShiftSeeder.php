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
                'start_time' => '07:00 AM',
                'end_time' => '03:00 PM',
                'status' => true
            ],
            [
                'name' => 'Evening Shift', 
                'start_time' => '03:00 PM',
                'end_time' => '11:00 PM',
                'status' => true
            ],
            [
                'name' => 'Night Shift',
                'start_time' => '11:00 PM', 
                'end_time' => '07:00 AM',
                'status' => false
            ]
        ];

        foreach ($shifts as $shift) {
            Shift::create($shift);
        }
    }
}