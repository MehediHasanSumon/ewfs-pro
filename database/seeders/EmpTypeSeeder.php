<?php

namespace Database\Seeders;

use App\Models\EmpType;
use Illuminate\Database\Seeder;

class EmpTypeSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->types() as $definition) {
            $type = EmpType::query()
                ->where('code', $definition['code'])
                ->orWhereIn('name', [
                    $definition['name'],
                    ...$definition['legacy_names'],
                ])
                ->first() ?? new EmpType;

            $type->fill([
                'code' => $definition['code'],
                'name' => $definition['name'],
                'status' => $definition['status'],
            ])->save();
        }
    }

    private function types(): array
    {
        return [
            ['code' => 'DIRECTOR', 'name' => 'Director', 'legacy_names' => [], 'status' => true],
            ['code' => 'MANAGER', 'name' => 'Manager', 'legacy_names' => [], 'status' => true],
            ['code' => 'HR-ADMIN', 'name' => 'HR Admin', 'legacy_names' => [], 'status' => true],
            ['code' => 'CASHIER', 'name' => 'Cashier', 'legacy_names' => ['Casher'], 'status' => true],
            ['code' => 'SALES', 'name' => 'Sales Executive', 'legacy_names' => ['Sells Man'], 'status' => true],
            ['code' => 'DRIVER', 'name' => 'Driver', 'legacy_names' => [], 'status' => true],
            ['code' => 'SUPPORT', 'name' => 'Support Staff', 'legacy_names' => ['Helper'], 'status' => true],
            ['code' => 'CLEANER', 'name' => 'Cleaner', 'legacy_names' => ['Clenar'], 'status' => true],
        ];
    }
}
