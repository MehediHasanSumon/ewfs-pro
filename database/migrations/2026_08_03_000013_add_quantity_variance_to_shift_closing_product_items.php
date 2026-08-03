<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shift_closing_product_items', function (Blueprint $table) {
            $table->decimal('recorded_quantity', 24, 6)
                ->default(0)
                ->after('quantity');
            $table->decimal('quantity_variance', 24, 6)
                ->default(0)
                ->after('recorded_quantity');
        });

        $this->dropValueConstraint();
        $this->addValueConstraint(
            allowZeroQuantity: true,
            includeRecordedQuantity: true
        );
    }

    public function down(): void
    {
        $hasZeroQuantity = DB::table('shift_closing_product_items')
            ->where('quantity', '<=', 0)
            ->exists();

        $this->dropValueConstraint();

        Schema::table('shift_closing_product_items', function (Blueprint $table) {
            $table->dropColumn([
                'recorded_quantity',
                'quantity_variance',
            ]);
        });

        $this->addValueConstraint(
            allowZeroQuantity: $hasZeroQuantity,
            includeRecordedQuantity: false
        );
    }

    private function dropValueConstraint(): void
    {
        $driver = DB::getDriverName();

        if (! in_array($driver, ['mysql', 'pgsql'], true)) {
            return;
        }

        $dropStatement = $driver === 'mysql'
            ? 'ALTER TABLE shift_closing_product_items DROP CHECK scpi_values_chk'
            : 'ALTER TABLE shift_closing_product_items DROP CONSTRAINT IF EXISTS scpi_values_chk';
        DB::statement($dropStatement);
    }

    private function addValueConstraint(
        bool $allowZeroQuantity,
        bool $includeRecordedQuantity
    ): void {
        if (! in_array(DB::getDriverName(), ['mysql', 'pgsql'], true)) {
            return;
        }

        $quantityOperator = $allowZeroQuantity ? '>=' : '>';
        $recordedConstraint = $includeRecordedQuantity
            ? ' AND recorded_quantity >= 0'
            : '';

        DB::statement(
            "ALTER TABLE shift_closing_product_items
             ADD CONSTRAINT scpi_values_chk
             CHECK (
                unit_price >= 0
                AND quantity {$quantityOperator} 0
                {$recordedConstraint}
                AND line_total >= 0
             )"
        );
    }
};
