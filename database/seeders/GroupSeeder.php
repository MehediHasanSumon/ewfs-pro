<?php

namespace Database\Seeders;

use App\Helpers\AccountGroupHelper;
use App\Models\Group;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class GroupSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $pending = AccountGroupHelper::systemGroups();
            $resolved = [];

            while ($pending !== []) {
                $progress = false;

                foreach ($pending as $key => $definition) {
                    $parentKey = $definition['parent'];

                    if ($parentKey !== null && ! isset($resolved[$parentKey])) {
                        continue;
                    }

                    $parentId = $parentKey === null
                        ? null
                        : $resolved[$parentKey]->id;
                    $this->assertNameAvailable(
                        $definition['code'],
                        $definition['name'],
                        $parentId
                    );

                    $group = Group::query()
                        ->where('code', $definition['code'])
                        ->lockForUpdate()
                        ->first();
                    $attributes = [
                        'parent_id' => $parentId,
                        'name' => $definition['name'],
                        'account_class' => $definition['account_class'],
                        'normal_balance' => $definition['normal_balance'],
                        'is_system' => true,
                        'status' => true,
                    ];

                    if ($group) {
                        $group->update($attributes);
                    } else {
                        $group = Group::query()->create([
                            'code' => $definition['code'],
                            ...$attributes,
                        ]);
                    }

                    $resolved[$key] = $group;
                    unset($pending[$key]);
                    $progress = true;
                }

                if (! $progress) {
                    throw new RuntimeException(
                        'ERP account group hierarchy contains an unresolved parent or cycle.'
                    );
                }
            }
        });
    }

    private function assertNameAvailable(
        string $code,
        string $name,
        ?int $parentId
    ): void {
        $conflict = Group::query()
            ->where('parent_id', $parentId)
            ->where('name', $name)
            ->where('code', '<>', $code)
            ->exists();

        if ($conflict) {
            throw new RuntimeException(
                "Cannot synchronize ERP account group [{$code}]: "
                ."the name [{$name}] is already used under the configured parent."
            );
        }
    }
}
