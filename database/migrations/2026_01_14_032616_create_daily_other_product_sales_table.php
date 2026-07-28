<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_closing_product_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_closing_id')->constrained('shift_closings')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->restrictOnDelete();
            $table->foreignId('sale_item_id')->nullable()->unique()->constrained('sale_items')->restrictOnDelete();
            $table->foreignId('inventory_movement_id')->nullable()->unique()->constrained('inventory_movements')->restrictOnDelete();
            $table->string('product_name_snapshot', 150);
            $table->string('unit_name_snapshot', 100);
            $table->decimal('unit_price', 24, 6);
            $table->decimal('quantity', 24, 6);
            $table->decimal('line_total', 24, 4);
            $table->timestamps();

            $table->index(['shift_closing_id', 'product_id'], 'scpi_closing_product_idx');
            $table->index(['employee_id', 'shift_closing_id'], 'scpi_employee_closing_idx');
        });

        DB::statement('ALTER TABLE shift_closing_product_items ADD CONSTRAINT scpi_values_chk CHECK (unit_price >= 0 AND quantity > 0 AND line_total >= 0)');

        DB::unprepared("
            CREATE TRIGGER shift_closing_product_items_before_update
            BEFORE UPDATE ON shift_closing_product_items
            FOR EACH ROW
            BEGIN
                IF (SELECT status FROM shift_closings WHERE id = OLD.shift_closing_id) <> 'draft' THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Closed shift product items are immutable';
                END IF;
            END
        ");

        DB::unprepared("
            CREATE TRIGGER shift_closing_product_items_before_delete
            BEFORE DELETE ON shift_closing_product_items
            FOR EACH ROW
            BEGIN
                IF (SELECT status FROM shift_closings WHERE id = OLD.shift_closing_id) <> 'draft' THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Closed shift product items cannot be deleted';
                END IF;
            END
        ");
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS shift_closing_product_items_before_delete');
        DB::unprepared('DROP TRIGGER IF EXISTS shift_closing_product_items_before_update');
        Schema::dropIfExists('shift_closing_product_items');
    }
};
