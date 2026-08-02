<?php

namespace App\Services;

use App\Helpers\ErpHelper;
use App\Models\Category;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CatalogReferenceService
{
    public function deleteCategory(Category $category): void
    {
        $this->deleteManyCategories([$category->getKey()]);
    }

    public function deleteManyCategories(array $ids): int
    {
        return $this->deleteMany(
            Category::class,
            $ids,
            ['products', 'saleItems', 'creditSaleItems'],
            'category'
        );
    }

    public function deleteUnit(Unit $unit): void
    {
        $this->deleteManyUnits([$unit->getKey()]);
    }

    public function deleteManyUnits(array $ids): int
    {
        return $this->deleteMany(
            Unit::class,
            $ids,
            ['products', 'saleItems', 'purchaseItems', 'creditSaleItems', 'shiftClosingProductItems'],
            'unit'
        );
    }

    private function deleteMany(
        string $modelClass,
        array $ids,
        array $blockingRelations,
        string $label
    ): int {
        return DB::transaction(function () use ($modelClass, $ids, $blockingRelations, $label): int {
            /** @var Collection<int, Model> $records */
            $records = $modelClass::query()->whereKey($ids)->lockForUpdate()->get();

            foreach ($records as $record) {
                if (
                    $record instanceof Category
                    && ErpHelper::isReservedCategoryCode($record->code)
                ) {
                    throw ValidationException::withMessages([
                        'ids' => 'Reserved ERP categories cannot be deleted.',
                    ]);
                }

                foreach ($blockingRelations as $relation) {
                    if ($record->{$relation}()->exists()) {
                        throw ValidationException::withMessages([
                            'ids' => "The selected {$label} is already used by operational records and cannot be deleted.",
                        ]);
                    }
                }
            }

            $count = $records->count();
            $modelClass::query()->whereKey($records->modelKeys())->delete();

            return $count;
        });
    }
}
