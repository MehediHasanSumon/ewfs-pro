<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->restrictOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->restrictOnDelete();
            $table->date('business_date');
            $table->timestamp('occurred_at');
            $table->string('movement_type', 64);
            $table->decimal('quantity_in', 24, 6)->default(0);
            $table->decimal('quantity_out', 24, 6)->default(0);
            $table->decimal('unit_cost', 24, 6)->default(0);
            $table->decimal('total_cost', 24, 4)->default(0);
            $table->string('source_type', 100);
            $table->unsignedBigInteger('source_id')->nullable();
            $table->unsignedBigInteger('source_line_id')->nullable();
            $table->foreignId('reversal_of_id')->nullable()->constrained('inventory_movements')->restrictOnDelete();
            $table->string('idempotency_key', 150)->unique();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['product_id', 'business_date', 'id'], 'im_product_date_id_idx');
            $table->index(['business_date', 'movement_type', 'id'], 'im_date_type_id_idx');
            $table->index(['shift_id', 'business_date', 'id'], 'im_shift_date_id_idx');
            $table->index(['source_type', 'source_id', 'source_line_id'], 'im_source_idx');
            $table->index(['journal_entry_id', 'id'], 'im_journal_idx');
        });

        Schema::create('stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained('products')->cascadeOnDelete();
            $table->decimal('opening_stock', 24, 6)->default(0);
            $table->decimal('current_stock', 24, 6)->default(0);
            $table->decimal('reserved_stock', 24, 6)->default(0);
            $table->decimal('available_stock', 24, 6)->default(0);
            $table->decimal('minimum_stock', 24, 6)->default(0);
            $table->decimal('maximum_stock', 24, 6)->nullable();
            $table->unsignedBigInteger('last_movement_id')->nullable();
            $table->unsignedBigInteger('version')->default(0);
            $table->timestamp('refreshed_at')->nullable();
            $table->timestamps();

            $table->index(['available_stock', 'product_id']);
            $table->index(['refreshed_at', 'product_id']);
        });

        Schema::create('inventory_daily_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->date('balance_date');
            $table->decimal('opening_quantity', 24, 6)->default(0);
            $table->decimal('quantity_in', 24, 6)->default(0);
            $table->decimal('quantity_out', 24, 6)->default(0);
            $table->decimal('closing_quantity', 24, 6)->default(0);
            $table->decimal('closing_value', 24, 4)->default(0);
            $table->unsignedBigInteger('last_movement_id')->nullable();
            $table->timestamp('refreshed_at')->nullable();

            $table->unique(['product_id', 'balance_date']);
            $table->index(['balance_date', 'product_id'], 'idb_date_product_idx');
        });

        DB::statement('ALTER TABLE inventory_movements ADD CONSTRAINT im_quantity_direction_chk CHECK ((quantity_in > 0 AND quantity_out = 0) OR (quantity_out > 0 AND quantity_in = 0))');
        DB::statement('ALTER TABLE inventory_movements ADD CONSTRAINT im_cost_values_chk CHECK (unit_cost >= 0 AND total_cost >= 0)');
        DB::statement('ALTER TABLE stocks ADD CONSTRAINT stock_values_chk CHECK (opening_stock >= 0 AND current_stock >= 0 AND reserved_stock >= 0 AND available_stock >= 0 AND minimum_stock >= 0 AND (maximum_stock IS NULL OR maximum_stock >= minimum_stock))');

        DB::unprepared("
            CREATE TRIGGER inventory_movements_before_update
            BEFORE UPDATE ON inventory_movements
            FOR EACH ROW
            BEGIN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Inventory movements are immutable; create a reversal movement';
            END
        ");

        DB::unprepared("
            CREATE TRIGGER inventory_movements_before_delete
            BEFORE DELETE ON inventory_movements
            FOR EACH ROW
            BEGIN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Inventory movements cannot be deleted';
            END
        ");
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS inventory_movements_before_delete');
        DB::unprepared('DROP TRIGGER IF EXISTS inventory_movements_before_update');

        Schema::dropIfExists('inventory_daily_balances');
        Schema::dropIfExists('stocks');
        Schema::dropIfExists('inventory_movements');
    }
};
