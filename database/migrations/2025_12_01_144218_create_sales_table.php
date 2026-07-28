<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_id')->constrained('shifts')->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->restrictOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->restrictOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->unique()->constrained('journal_entries')->restrictOnDelete();
            $table->enum('sale_type', ['regular', 'white'])->default('regular');
            $table->date('sale_date');
            $table->time('sale_time');
            $table->string('invoice_no', 100)->unique();
            $table->string('memo_no', 150)->nullable();
            $table->string('customer_name_snapshot', 150)->nullable();
            $table->string('customer_mobile_snapshot', 50)->nullable();
            $table->string('company_name_snapshot', 255)->nullable();
            $table->string('proprietor_name_snapshot', 255)->nullable();
            $table->string('vehicle_number_snapshot', 50)->nullable();
            $table->decimal('subtotal', 24, 4);
            $table->decimal('discount_total', 24, 4)->default(0);
            $table->decimal('tax_total', 24, 4)->default(0);
            $table->decimal('grand_total', 24, 4);
            $table->enum('status', ['draft', 'posted', 'partially_paid', 'paid', 'void'])->default('draft');
            $table->boolean('is_send_sms')->default(false);
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->index(['sale_date', 'status', 'id'], 'sale_date_status_idx');
            $table->index(['shift_id', 'sale_date', 'status', 'id'], 'sale_shift_date_idx');
            $table->index(['customer_id', 'sale_date', 'status', 'id'], 'sale_customer_date_idx');
            $table->index(['vehicle_id', 'sale_date', 'id'], 'sale_vehicle_date_idx');
            $table->index(['sale_type', 'sale_date', 'status'], 'sale_type_date_idx');
        });

        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->unsignedSmallInteger('line_no');
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->string('product_code_snapshot', 64)->nullable();
            $table->string('product_name_snapshot', 150);
            $table->string('category_name_snapshot', 100);
            $table->string('unit_name_snapshot', 100);
            $table->decimal('quantity', 24, 6);
            $table->decimal('unit_price', 24, 6);
            $table->decimal('unit_cost', 24, 6)->default(0);
            $table->decimal('discount_amount', 24, 4)->default(0);
            $table->decimal('tax_amount', 24, 4)->default(0);
            $table->decimal('line_total', 24, 4);
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->unique(['sale_id', 'line_no']);
            $table->index(['product_id', 'sale_id'], 'sale_item_product_idx');
        });

        DB::statement('ALTER TABLE sales ADD CONSTRAINT sale_totals_chk CHECK (subtotal >= 0 AND discount_total >= 0 AND tax_total >= 0 AND grand_total >= 0)');
        DB::statement('ALTER TABLE sale_items ADD CONSTRAINT sale_item_values_chk CHECK (quantity > 0 AND unit_price >= 0 AND unit_cost >= 0 AND discount_amount >= 0 AND tax_amount >= 0 AND line_total >= 0)');

        DB::unprepared("
            CREATE TRIGGER sale_items_before_insert
            BEFORE INSERT ON sale_items
            FOR EACH ROW
            BEGIN
                IF (SELECT status FROM sales WHERE id = NEW.sale_id) <> 'draft' THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Sale items can only be added to draft sales';
                END IF;
            END
        ");

        DB::unprepared("
            CREATE TRIGGER sale_items_before_update
            BEFORE UPDATE ON sale_items
            FOR EACH ROW
            BEGIN
                IF (SELECT status FROM sales WHERE id = OLD.sale_id) <> 'draft' THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Posted sale items are immutable';
                END IF;
            END
        ");

        DB::unprepared("
            CREATE TRIGGER sale_items_before_delete
            BEFORE DELETE ON sale_items
            FOR EACH ROW
            BEGIN
                IF (SELECT status FROM sales WHERE id = OLD.sale_id) <> 'draft' THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Posted sale items cannot be deleted';
                END IF;
            END
        ");

        DB::unprepared("
            CREATE TRIGGER sales_before_update
            BEFORE UPDATE ON sales
            FOR EACH ROW
            BEGIN
                IF OLD.status <> 'draft' THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Posted sales are immutable';
                END IF;

                IF NEW.status = 'posted' AND (
                    NEW.journal_entry_id IS NULL OR
                    (SELECT status FROM journal_entries WHERE id = NEW.journal_entry_id) <> 'posted'
                ) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'A sale can only post with a posted journal entry';
                END IF;
            END
        ");

        DB::unprepared("
            CREATE TRIGGER sales_before_delete
            BEFORE DELETE ON sales
            FOR EACH ROW
            BEGIN
                IF OLD.status <> 'draft' THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Only draft sales can be deleted';
                END IF;
            END
        ");
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS sales_before_delete');
        DB::unprepared('DROP TRIGGER IF EXISTS sales_before_update');
        DB::unprepared('DROP TRIGGER IF EXISTS sale_items_before_delete');
        DB::unprepared('DROP TRIGGER IF EXISTS sale_items_before_update');
        DB::unprepared('DROP TRIGGER IF EXISTS sale_items_before_insert');
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
    }
};
