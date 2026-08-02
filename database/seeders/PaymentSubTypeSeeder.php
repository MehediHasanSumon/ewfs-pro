<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class PaymentSubTypeSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(SystemVoucherTransactionTypeSeeder::class);
    }
}
