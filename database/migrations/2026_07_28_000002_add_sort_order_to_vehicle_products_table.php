<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicle_products', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->nullable()->after('product_id');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'WITH ranked AS (
                    SELECT id, ROW_NUMBER() OVER (
                        PARTITION BY vehicle_id ORDER BY id
                    ) AS position
                    FROM vehicle_products
                )
                UPDATE vehicle_products AS assignment
                SET sort_order = ranked.position
                FROM ranked
                WHERE assignment.id = ranked.id'
            );
        } elseif (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement(
                'UPDATE vehicle_products AS assignment
                INNER JOIN (
                    SELECT id, ROW_NUMBER() OVER (
                        PARTITION BY vehicle_id ORDER BY id
                    ) AS position
                    FROM vehicle_products
                ) AS ranked ON ranked.id = assignment.id
                SET assignment.sort_order = ranked.position'
            );
        } else {
            $currentVehicleId = null;
            $position = 0;

            DB::table('vehicle_products')
                ->orderBy('vehicle_id')
                ->orderBy('id')
                ->select(['id', 'vehicle_id'])
                ->each(function (object $assignment) use (&$currentVehicleId, &$position): void {
                    if ($currentVehicleId !== $assignment->vehicle_id) {
                        $currentVehicleId = $assignment->vehicle_id;
                        $position = 0;
                    }

                    $position++;

                    DB::table('vehicle_products')
                        ->where('id', $assignment->id)
                        ->update(['sort_order' => $position]);
                });
        }

        Schema::table('vehicle_products', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_products', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
