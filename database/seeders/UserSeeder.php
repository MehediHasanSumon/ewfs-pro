<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->delete();
        
        $users = [
            [
                'name' => 'Md Mehedi Hasan',
                'email' => 'mehedihassan2992001@gmail.com',
                'password' => Hash::make('ewrps76zfZ@X7'),
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Maria',
                'email' => 'maria@gmail.com',
                'password' => Hash::make('ewrps76zfZ@X7'),
                'email_verified_at' => now(),
            ]
        ];

        foreach ($users as $user) {
            User::create($user);
        }
    }
}