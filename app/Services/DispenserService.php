<?php

namespace App\Services;

use App\Models\Dispenser;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DispenserService
{
    public function create(array $attributes): Dispenser
    {
        return DB::transaction(function () use ($attributes): Dispenser {
            $this->assertAllowedProduct((int) $attributes['product_id']);
            $attributes['dispenser_item'] = $attributes['product_id'];
            $attributes['opening_reading'] = $attributes['opening_reading'] ?? 0;

            return Dispenser::create($attributes);
        });
    }

    public function update(Dispenser $dispenser, array $attributes): Dispenser
    {
        return DB::transaction(function () use ($dispenser, $attributes): Dispenser {
            $lockedDispenser = Dispenser::query()->lockForUpdate()->findOrFail($dispenser->getKey());
            $this->assertAllowedProduct((int) $attributes['product_id']);
            $hasReadings = $lockedDispenser->readings()->exists();

            if ($hasReadings && (int) $attributes['product_id'] !== (int) $lockedDispenser->product_id) {
                throw ValidationException::withMessages([
                    'product_id' => 'A dispenser with shift readings cannot be assigned to another product.',
                ]);
            }

            $openingReading = $attributes['opening_reading'] ?? $lockedDispenser->opening_reading;
            if ($hasReadings && abs((float) $openingReading - (float) $lockedDispenser->opening_reading) > 0.000001) {
                throw ValidationException::withMessages([
                    'opening_reading' => 'Opening reading cannot be changed after shift readings exist.',
                ]);
            }

            $attributes['dispenser_item'] = $attributes['product_id'];
            $attributes['opening_reading'] = $openingReading;
            $lockedDispenser->update($attributes);

            return $lockedDispenser->refresh();
        });
    }

    public function delete(Dispenser $dispenser): void
    {
        DB::transaction(function () use ($dispenser): void {
            $lockedDispenser = Dispenser::query()->lockForUpdate()->findOrFail($dispenser->getKey());

            if ($lockedDispenser->readings()->exists()) {
                throw ValidationException::withMessages([
                    'dispenser' => 'A dispenser with shift readings cannot be deleted.',
                ]);
            }

            $lockedDispenser->delete();
        });
    }

    public function deleteMany(array $ids): int
    {
        return DB::transaction(function () use ($ids): int {
            $dispensers = Dispenser::query()->whereKey($ids)->lockForUpdate()->get();

            if ($dispensers->contains(fn (Dispenser $dispenser) => $dispenser->readings()->exists())) {
                throw ValidationException::withMessages([
                    'ids' => 'One or more selected dispensers have shift readings and cannot be deleted.',
                ]);
            }

            $count = $dispensers->count();
            Dispenser::query()->whereKey($dispensers->modelKeys())->delete();

            return $count;
        });
    }

    private function assertAllowedProduct(int $productId): void
    {
        if (! Product::query()
            ->whereKey($productId)
            ->allowedForDispenser()
            ->exists()
        ) {
            throw ValidationException::withMessages([
                'product_id' => 'The selected product category is not allowed for dispenser assignment.',
            ]);
        }
    }
}
